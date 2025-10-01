<?php

namespace App\Service;

use App\DTO\MoulinetteRequestDTO;
use App\DTO\MoulinetteResultDTO;
use App\Entity\Agency;
use App\Exception\FileValidationException;
use App\Exception\AgencyNotFoundException;
use App\Exception\MoulinetteException;
use App\Repository\AgencyRepository;
use Psr\Log\LoggerInterface;

class MoulinetteService
{
    private FileValidationService $fileValidator;
    private ExcelReaderService $excelReader;
    private RubricProcessingService $rubricProcessor;
    private ExcelWriterService $excelWriter;
    private AgencyRepository $agencyRepository;
    private LoggerInterface $logger;

    public function __construct(
        FileValidationService $fileValidator,
        ExcelReaderService $excelReader,
        RubricProcessingService $rubricProcessor,
        ExcelWriterService $excelWriter,
        AgencyRepository $agencyRepository,
        LoggerInterface $logger
    ) {
        $this->fileValidator = $fileValidator;
        $this->excelReader = $excelReader;
        $this->rubricProcessor = $rubricProcessor;
        $this->excelWriter = $excelWriter;
        $this->agencyRepository = $agencyRepository;
        $this->logger = $logger;
    }

    public function processMoulinette(MoulinetteRequestDTO $request): MoulinetteResultDTO
    {
        try {
            $this->logger->info('Début du traitement de la moulinette', [
                'agency_id' => $request->getAgencyId()
            ]);

            $this->validateFiles($request);

            $agency = $this->getAgencyWithRubrics($request->getAgencyId());

            $filesData = $this->readFiles($request);

            $calculatedValues = $this->rubricProcessor->processRubrics(
                $filesData['cotisation'],
                $filesData['rubrique'],
                $filesData['matricule'],
                $agency->getAgencyRubrics()->toArray()
            );

            $outputResult = $this->excelWriter->generateOutputFile(
                $request->getFiles()->getOutputFile(),
                $calculatedValues
            );

            $this->logger->info('Traitement de la moulinette terminé avec succès', [
                'agency_id' => $request->getAgencyId(),
                'values_written' => $outputResult['written_count']
            ]);

            return MoulinetteResultDTO::success(
                $outputResult['response'],
                $outputResult['written_count']
            );

        } catch (FileValidationException $e) {
            $this->logger->warning('Erreur de validation des fichiers', [
                'errors' => $e->getErrors()
            ]);
            return MoulinetteResultDTO::error($e->getErrorsAsString());

        } catch (AgencyNotFoundException $e) {
            $this->logger->warning('Agence non trouvée', [
                'agency_id' => $request->getAgencyId(),
                'message' => $e->getMessage()
            ]);
            return MoulinetteResultDTO::error($e->getMessage());

        } catch (MoulinetteException $e) {
            $this->logger->error('Erreur lors du traitement de la moulinette', [
                'agency_id' => $request->getAgencyId(),
                'message' => $e->getMessage()
            ]);
            return MoulinetteResultDTO::error($e->getMessage());

        } catch (\Exception $e) {
            $this->logger->error('Erreur inattendue lors du traitement de la moulinette', [
                'agency_id' => $request->getAgencyId(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return MoulinetteResultDTO::error('Une erreur inattendue est survenue lors du traitement.');
        }
    }

    private function validateFiles(MoulinetteRequestDTO $request): void
    {
        $files = [
            'cotisation_file' => $request->getFiles()->getCotisationFile(),
            'matricule_file' => $request->getFiles()->getMatriculeFile(),
            'rubrique_file' => $request->getFiles()->getRubriqueFile(),
            'output_file' => $request->getFiles()->getOutputFile(),
        ];

        $errors = $this->fileValidator->validateUploadedFiles($files);

        if (!empty($errors)) {
            throw new FileValidationException($errors);
        }
    }

    private function getAgencyWithRubrics(?int $agencyId): Agency
    {
        if (!$agencyId) {
            throw new AgencyNotFoundException(0);
        }

        $agency = $this->agencyRepository->find($agencyId);

        if (!$agency || $agency->getAgencyRubrics()->isEmpty()) {
            throw new AgencyNotFoundException($agencyId);
        }

        return $agency;
    }

    private function readFiles(MoulinetteRequestDTO $request): array
    {
        try {
            return [
                'cotisation' => $this->excelReader->readAllRows($request->getFiles()->getCotisationFile()),
                'rubrique' => $this->excelReader->readAllRows($request->getFiles()->getRubriqueFile()),
                'matricule' => $this->excelReader->readAllRows($request->getFiles()->getMatriculeFile()),
            ];
        } catch (\Exception $e) {
            throw new MoulinetteException("Erreur lors de la lecture des fichiers : " . $e->getMessage(), 0, $e);
        }
    }
}