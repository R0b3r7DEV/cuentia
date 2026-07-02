# 13 — Testing the finance logic · Tests de la lógica fiscal

Goal / Objetivo: unit-test the VAT and IRPF math so it's provably correct and stays correct.

> We test the **finance moat** first: the tax logic is where a bug costs the most, and it's pure, so it's
> the easiest and most valuable thing to test. / Probamos primero el **foso financiero**: la lógica fiscal
> es donde más cuesta un error, y es pura, así que es lo más fácil y valioso de testear.

---

## 1. Install PHPUnit

```powershell
composer require --dev symfony/test-pack
```

Run the suite with:

```powershell
php bin/phpunit
```

## 2. Pure logic = easy tests (no database)

- **EN:** `VatService` and `IrpfService` compute from a list of transactions. In the tests we build
  `Transaction` + `Category` objects **in memory** and feed them through a **stub** repository, so no
  database is needed. Pure logic is fast and deterministic to test — that's *why* we kept the tax math
  free of I/O.
- **ES:** `VatService` e `IrpfService` calculan a partir de una lista de movimientos. En los tests
  construimos objetos `Transaction` + `Category` **en memoria** y los pasamos por un **stub** del
  repositorio, así no hace falta base de datos. La lógica pura es rápida y determinista de testear — por
  eso mantuvimos el cálculo fiscal libre de I/O.

```php
private function service(array $transactions): VatService
{
    $repo = $this->createStub(TransactionRepository::class);
    $repo->method('findAll')->willReturn($transactions);
    return new VatService($repo);
}
```

> **Stub vs mock:** we use `createStub()` (not `createMock()`) because we only need canned return values,
> not to assert calls — PHPUnit 13 recommends exactly this. / Usamos `createStub()` (no `createMock()`)
> porque solo necesitamos valores de retorno, no verificar llamadas — es lo que recomienda PHPUnit 13.

## 3. What we assert

`tests/Service/VatServiceTest.php`
- The rate + base for a 21% expense (121 → base 100.00).
- An **exempt** category (`Nómina`) has **no** VAT.
- The full summary: output 336.00, input 23.00, **net 313.00**.

`tests/Service/IrpfServiceTest.php`
- Q1 net 1153.21 → **payment 230.64** (20%).
- A salary-only year prepays **0.00** (salary isn't self-employment income).
- The **next deadline** countdown (Q2, 18 days from 2026-07-02).

## 4. Run it

```powershell
php bin/phpunit
# OK (6 tests, 14 assertions)
```

- **EN:** Green. These tests double as living documentation of the tax rules, and they'll catch any
  future change that breaks the numbers.
- **ES:** En verde. Estos tests son además documentación viva de las reglas fiscales y detectarán
  cualquier cambio futuro que rompa los números.

## Why it matters for the portfolio

- **EN:** Being able to say *"the VAT and IRPF logic is unit-tested, here's why salaries are excluded"* in
  an interview is far stronger than a screenshot. Tests signal an engineer, not just a coder.
- **ES:** Poder decir en una entrevista *"la lógica de IVA e IRPF está testeada, y por esto se excluye la
  nómina"* es mucho más potente que una captura. Los tests te presentan como ingeniero, no solo coder.

---

**Next / Siguiente:** CI (run tests on every push) and a live deploy, or the Norma 43 importer.
/ CI (ejecutar los tests en cada push) y un deploy en vivo, o el importador Norma 43.
