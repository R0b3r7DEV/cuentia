<?php

namespace App\Tests\Assistant;

use App\Assistant\AssistantException;
use App\Assistant\ClaudeGateway;
use App\Assistant\CieAssistant;
use App\Entity\User;
use App\Service\CredentialStore;
use App\Service\ImageSanitizer;
use App\Tests\Service\ImageSanitizerTest;
use PHPUnit\Framework\TestCase;

class CieAssistantTest extends TestCase
{
    private function assistant(ClaudeGateway $claude, string $key = 'sk-test'): CieAssistant
    {
        $credentials = $this->createStub(CredentialStore::class);
        $credentials->method('anthropicKey')->willReturn($key);

        return new CieAssistant($claude, $credentials, new ImageSanitizer());
    }

    /** Everything sent to Claude in the last call, as one string. */
    private static function sent(FakeClaude $claude): string
    {
        $call = end($claude->calls);

        return $call['system'] . json_encode($call['content'], JSON_UNESCAPED_UNICODE);
    }

    public function testReadsABillPhotoIntoCieFieldsAndChecksWhatRulesCanCheck(): void
    {
        $claude = new FakeClaude([
            'titularName' => 'María López García', 'titularNif' => '12345678-z',
            'distributor' => 'i-DE Redes Eléctricas Inteligentes', 'streetType' => 'Calle', 'streetName' => 'del Mar',
            'portal' => '3', 'stair' => null, 'floor' => '2º', 'door' => 'B', 'postalCode' => '12001', 'locality' => 'Castelló de la Plana', 'province' => 'Castellón',
            'cups' => 'ES 0021 0000 1234 5679 LB', // one digit misread
            'contractedPowerKw' => '5,75', 'supplyType' => 'monofasico',
        ]);

        $result = $this->assistant($claude)->readBill(new User(), ImageSanitizerTest::jpeg(800, 600));

        self::assertSame('María López García', $result['fields']['titularName']);
        self::assertSame('12345678Z', $result['fields']['titularNif']);
        self::assertSame('del Mar', $result['fields']['streetName']);
        self::assertSame('Calle', $result['fields']['streetType']);
        self::assertSame('3', $result['fields']['portal']);
        self::assertArrayNotHasKey('stair', $result['fields'], 'a part that is not on the bill stays empty');
        self::assertSame('i-DE Redes Eléctricas Inteligentes', $result['fields']['distributor']);
        self::assertStringContainsString('no la comercializadora', $claude->calls[0]['system']);
        self::assertSame('monofasico', $result['fields']['supplyType']);
        self::assertSame('5.750', $result['contractedPowerKw']);
        self::assertSame(['cups'], $result['checks'], 'the misread CUPS is flagged by its check letters');

        $call = $claude->calls[0];
        self::assertSame('low', $call['effort']);
        self::assertSame('image', $call['content'][0]['type']);
        self::assertSame('image/jpeg', $call['content'][0]['source']['mediaType'], 'the photo is re-encoded before it is sent');
        self::assertFalse($call['schema']['additionalProperties']);
    }

    public function testAPdfBillIsSentAsADocument(): void
    {
        $claude = new FakeClaude(array_fill_keys(['titularName', 'titularNif', 'distributor', 'streetType', 'streetName', 'portal', 'stair', 'floor', 'door', 'postalCode', 'locality', 'province', 'cups', 'contractedPowerKw', 'supplyType'], null));
        $result = $this->assistant($claude)->readBill(new User(), "%PDF-1.7\nfake");

        self::assertSame('document', $claude->calls[0]['content'][0]['type']);
        self::assertSame('application/pdf', $claude->calls[0]['content'][0]['source']['mediaType']);
        self::assertSame([], $result['fields'], 'nothing read, nothing invented');
        self::assertNull($result['contractedPowerKw']);
    }

    public function testAFileThatIsNotABillImageIsRejectedBeforeCallingClaude(): void
    {
        $claude = new FakeClaude([]);
        try {
            $this->assistant($claude)->readBill(new User(), '<?php echo 1;');
            self::fail('should reject');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('invalid_image', $e->getMessage());
        }
        self::assertSame([], $claude->calls);
    }

    /** Help and review never send the client's name, NIF, address or CUPS. */
    public function testHelpAndReviewSendNoPersonalDataOfTheClient(): void
    {
        $form = [
            'titularName' => 'María López García', 'titularNif' => '12345678Z', 'titularAddress' => 'C/ Sol 9',
            'address' => 'C/ Mar 3', 'postalCode' => '12001', 'cups' => 'ES0021000012345678LB',
            'streetName' => 'Calle Inventada', 'portal' => '77', 'specificRisks' => ['humedo'],
            'maxPower' => '5.75', 'igaCurrent' => '25', 'supplyType' => 'monofasico',
        ];

        $help = new FakeClaude(['answer' => 'El IGA es el interruptor general…']);
        self::assertSame('El IGA es el interruptor general…', $this->assistant($help)->help(new User(), 'protections', '¿Qué es el IGA?', $form));

        $review = new FakeClaude(['summary' => 'Bien', 'items' => []]);
        $this->assistant($review)->review(new User(), $form, []);

        foreach ([$help, $review] as $claude) {
            $sent = self::sent($claude);
            foreach (['María', '12345678Z', 'C/ Sol', 'C/ Mar', '12001', 'ES0021', 'Inventada'] as $personal) {
                self::assertStringNotContainsString($personal, $sent);
            }
            self::assertStringContainsString('Intensidad del IGA (A): 25', $sent, 'the technical data does go');
            self::assertStringContainsString('Riesgos específicos de la instalación: humedo', $sent, 'lists are sent as text');
            self::assertStringContainsString('Nunca afirmes que una instalación "cumple"', $sent);
        }
        self::assertSame('medium', $review->calls[0]['effort']);
    }

    public function testReviewKeepsAtMostSixValidItems(): void
    {
        $items = array_fill(0, 9, ['field' => 'igaCurrent', 'severity' => 'warning', 'message' => 'Revisa el IGA']);
        $items[] = ['field' => 'inventedField', 'severity' => 'critical', 'message' => 'x'];
        $result = $this->assistant(new FakeClaude(['summary' => 'Ojo', 'items' => array_reverse($items)]))->review(new User(), [], ['address']);

        self::assertCount(6, $result['items']);
        self::assertNull($result['items'][0]['field'], 'an unknown field name is dropped');
        self::assertSame('info', $result['items'][0]['severity'], 'an unknown severity becomes info');
    }

    public function testEmptyQuestionIsRefusedWithoutCallingClaude(): void
    {
        $claude = new FakeClaude([]);
        $this->expectExceptionObject(new \InvalidArgumentException('question_required'));
        $this->assistant($claude)->help(new User(), 'client', '   ', []);
    }

    public function testNoKeyMeansDisabled(): void
    {
        self::assertFalse($this->assistant(new FakeClaude([]), '')->enabled(new User()));
    }

    public function testAnEmptyAnswerIsAnError(): void
    {
        $this->expectExceptionObject(new AssistantException('bad_output'));
        $this->assistant(new FakeClaude(['answer' => '  ']))->help(new User(), 'client', '¿Qué es el CUPS?', []);
    }
}
