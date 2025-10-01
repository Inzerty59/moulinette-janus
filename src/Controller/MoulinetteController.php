<?php

namespace App\Controller;

use App\DTO\MoulinetteFilesDTO;
use App\DTO\MoulinetteRequestDTO;
use App\Repository\AgencyRepository;
use App\Service\MoulinetteService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MoulinetteController extends AbstractController
{
    private MoulinetteService $moulinetteService;
    private LoggerInterface $logger;

    public function __construct(MoulinetteService $moulinetteService, LoggerInterface $logger)
    {
        $this->moulinetteService = $moulinetteService;
        $this->logger = $logger;
    }

    #[Route('/', name: 'app_home')]
    public function index(Request $request, AgencyRepository $agencyRepository): Response
    {
        $agencies = $agencyRepository->findAll();
        $message = null;
        $selectedAgency = null;
        $agencyRubrics = [];

        if ($request->isMethod('POST')) {
            try {
                $moulinetteRequest = $this->createMoulinetteRequest($request);
                $result = $this->moulinetteService->processMoulinette($moulinetteRequest);

                if ($result->isSuccess()) {
                    $this->logger->info('Moulinette traitée avec succès', [
                        'values_written' => $result->getWrittenValuesCount()
                    ]);
                    return $result->getResponse();
                } else {
                    $message = ['error', $result->getErrorMessage()];
                }

                $agencyId = $moulinetteRequest->getAgencyId();
                if ($agencyId) {
                    $selectedAgency = $agencyRepository->find($agencyId);
                    if ($selectedAgency) {
                        $agencyRubrics = $selectedAgency->getAgencyRubrics();
                    }
                }

            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la création de la requête', [
                    'error' => $e->getMessage()
                ]);
                $message = ['error', 'Erreur lors du traitement de la demande : ' . $e->getMessage()];
            }
        }

        return $this->render('moulinette/index.html.twig', [
            'agencies' => $agencies,
            'message' => $message,
            'selectedAgency' => $selectedAgency,
            'agencyRubrics' => $agencyRubrics,
        ]);
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function createMoulinetteRequest(Request $request): MoulinetteRequestDTO
    {
        $agencyId = $request->request->getInt('agency') ?: null;

        $requiredFiles = ['cotisation_file', 'matricule_file', 'rubrique_file', 'output_file'];
        $uploadedFiles = [];

        foreach ($requiredFiles as $fileKey) {
            $file = $request->files->get($fileKey);
            if (!$file) {
                throw new \InvalidArgumentException("Le fichier {$fileKey} est requis.");
            }
            $uploadedFiles[$fileKey] = $file;
        }

        $filesDTO = new MoulinetteFilesDTO(
            $uploadedFiles['cotisation_file'],
            $uploadedFiles['matricule_file'],
            $uploadedFiles['rubrique_file'],
            $uploadedFiles['output_file']
        );

        return new MoulinetteRequestDTO($agencyId, $filesDTO);
    }
}
