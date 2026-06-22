<?php

namespace App\Controller;

use App\DTO\GeiqFilesDTO;
use App\DTO\GeiqRequestDTO;
use App\Service\GeiqFileValidationService;
use App\Service\GeiqService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GeiqController extends AbstractController
{
    public function __construct(
        private readonly GeiqService $geiqService,
        private readonly GeiqFileValidationService $fileValidationService
    ) {
    }

    #[Route('/geiq', name: 'app_geiq')]
    #[IsGranted('ROLE_USER')]
    public function index(Request $request): Response
    {
        $message = null;
        $selectedTerritory = 'nord';

        if ($request->isMethod('POST')) {
            try {
                $geiqRequest = $this->createGeiqRequest($request);
                $selectedTerritory = $geiqRequest->getTerritory();
                $result = $this->geiqService->processGeiq($geiqRequest);

                if ($result->isSuccess()) {
                    return $result->getResponse();
                }

                $message = ['danger', $result->getErrorMessage()];
            } catch (\InvalidArgumentException $e) {
                $message = ['danger', $e->getMessage()];
            } catch (\Throwable $e) {
                $message = ['danger', 'Erreur lors du traitement GEIQ : ' . $e->getMessage()];
            }
        }

        return $this->render('geiq/index.html.twig', [
            'message' => $message,
            'selectedTerritory' => $selectedTerritory,
        ]);
    }

    private function createGeiqRequest(Request $request): GeiqRequestDTO
    {
        $territory = strtolower(trim((string) $request->request->get('territory', 'nord')));
        if (!in_array($territory, ['nord', 'idf'], true)) {
            throw new \InvalidArgumentException('Le territoire sélectionné est invalide.');
        }

        $files = [
            'control_file' => $request->files->get('control_file'),
            'dsn_file' => $request->files->get('dsn_file'),
            'totalisation_file' => $request->files->get('totalisation_file'),
        ];

        $errors = $this->fileValidationService->validateUploadedFiles($files);
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode('<br>', $errors));
        }

        return new GeiqRequestDTO(
            $territory,
            new GeiqFilesDTO(
                $files['control_file'],
                $files['dsn_file'],
                $files['totalisation_file']
            )
        );
    }
}
