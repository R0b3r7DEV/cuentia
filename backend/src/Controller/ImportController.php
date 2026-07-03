<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Import endpoint. Thin on purpose: it only reads the request and delegates the
 * real work to ImportService.
 * ES: Endpoint de importación. Fino a propósito: solo lee la petición y delega el
 * trabajo real en ImportService.
 */
class ImportController extends AbstractController
{
    #[Route('/api/import/csv', name: 'api_import_csv', methods: ['POST'])]
    public function importCsv(Request $request, ImportService $import, #[CurrentUser] User $user): JsonResponse
    {
        // Accept either an uploaded file (field "file") or the raw request body.
        // ES: Acepta un fichero subido (campo "file") o el cuerpo crudo de la petición.
        $file = $request->files->get('file');
        $csv = $file ? file_get_contents($file->getPathname()) : $request->getContent();

        if (!is_string($csv) || trim($csv) === '') {
            return $this->json(['error' => 'No CSV provided'], 400);
        }

        // Auto-detects CSV vs Norma 43 by content. / Auto-detecta CSV vs Norma 43 por el contenido.
        $result = $import->import($csv, $user);

        return $this->json($result);
    }
}
