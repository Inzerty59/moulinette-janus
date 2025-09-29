<?php

namespace App\Controller;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

final class MoulinetteController extends AbstractController
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/', name: 'app_home')]
    public function index(\App\Repository\AgencyRepository $agencyRepository): Response
    {
        $agencies = $agencyRepository->findAll();
            $message = null;

        $selectedAgency = null;
        $agencyRubrics = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $allowedMimeTypes = [
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
                    'application/vnd.ms-excel', // .xls
                    'text/csv',
                    'application/csv',
                    'text/plain',
                ];
                $files = [
                    'cotisation_file',
                    'matricule_file',
                    'rubrique_file',
                    'output_file',
                ];
                $errors = [];
                foreach ($files as $fileKey) {
                    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
                        $errors[] = "Le fichier $fileKey est manquant ou invalide.";
                        continue;
                    }
                    $type = $_FILES[$fileKey]['type'];
                    if (!in_array($type, $allowedMimeTypes)) {
                        $errors[] = "Le fichier $fileKey n'est pas un fichier Excel ou CSV valide.";
                    }
                }
            $agencyId = isset($_POST['agency']) ? (int)$_POST['agency'] : null;
            if ($agencyId) {
                $selectedAgency = $agencyRepository->find($agencyId);
                if ($selectedAgency) {
                    $agencyRubrics = $selectedAgency->getAgencyRubrics();
                }
            }

            if (empty($errors)) {
                $html = '';

                $html = '';

                $readAllRows = function($filePath) {
                    $spreadsheet = IOFactory::load($filePath);
                    $sheet = $spreadsheet->getActiveSheet();
                    $rows = [];
                    foreach ($sheet->getRowIterator() as $row) {
                        $cellIterator = $row->getCellIterator();
                        $cellIterator->setIterateOnlyExistingCells(false);
                        $rowData = [];
                        foreach ($cellIterator as $cell) {
                            $rowData[] = $cell->getValue();
                        }
                        $rows[] = $rowData;
                    }
                    return $rows;
                };

                $cotisationRows = $readAllRows($_FILES['cotisation_file']['tmp_name']);
                $rubriqueRows = $readAllRows($_FILES['rubrique_file']['tmp_name']);
                $matriculeRows = $readAllRows($_FILES['matricule_file']['tmp_name']);
                $outputRows = $readAllRows($_FILES['output_file']['tmp_name']);

                if ($selectedAgency && $agencyRubrics) {
                    $html = '<b>Correspondance rubriques agence (code, catégorie, nom, valeur trouvée) :</b><table border="1" cellpadding="3"><tr><th>Code</th><th>Catégorie</th><th>Nom</th><th>Valeur trouvée</th><th>Détail recherche</th></tr>';
                    $rubriqueHeader = isset($rubriqueRows[0]) ? array_map('strtolower', $rubriqueRows[0]) : [];
                    $isJALCOT = in_array('mont_base', $rubriqueHeader);
                    $normalize = function($str) {
                        $str = strtolower($str);
                        $str = preg_replace('/[\s\p{Zs}]+/u', '', $str);
                        $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
                        return $str;
                    };
                    foreach ($agencyRubrics as $rubric) {
                        $code = trim($rubric->getCode());
                        $cat = trim($rubric->getCategory());
                        $nom = trim($rubric->getName());
                        $valeur = '';
                        $detail = '';
                        $catNorm = $normalize($cat);
                        $found = false;
                        $sources = [
                            [
                                'rows' => $rubriqueRows,
                                'type' => 'rubrique',
                                'colMapping' => $isJALCOT ? [
                                    'base' => 4,
                                    'salarié montant' => 6,
                                    'patronal montant' => 8,
                                    'total' => 10,
                                ] : [
                                    'base' => 6,
                                    'à payer' => 7,
                                    'à retenir' => 8,
                                    'patronal montant' => 9,
                                    'total' => 11,
                                ]
                            ],
                            [
                                'rows' => $cotisationRows,
                                'type' => 'cotisation',
                                'colMapping' => [
                                    'base' => 4,
                                    'salarié montant' => 6,
                                    'patronal montant' => 8,
                                    'total' => 10,
                                ]
                            ],
                            [
                                'rows' => $matriculeRows,
                                'type' => 'matricule',
                                'colMapping' => [
                                    'base' => 2,
                                    'à payer' => 3,
                                    'à retenir' => 4,
                                    'patronal montant' => 5,
                                    'total' => 6,
                                ]
                            ],
                        ];
                        foreach ($sources as $source) {
                            $rows = $source['rows'];
                            $sourceType = $source['type'];
                            $colMapping = $source['colMapping'];
                            $colMontant = isset($colMapping[$catNorm]) ? $colMapping[$catNorm] : null;
                            if ($colMontant === null) continue;
                            foreach ($rows as $rowIdx => $row) {
                                if ($rowIdx === 0) continue;
                                if (isset($row[1]) && $normalize($row[1]) === $normalize($code)) {
                                    if (isset($row[$colMontant]) && $row[$colMontant] !== '' && $row[$colMontant] !== null) {
                                        $valeur = $rubric->formatValue($row[$colMontant]);
                                        $detail = 'Correspondance sur code (' . $code . ') et catégorie (' . $cat . ') dans le fichier ' . $sourceType . ' en colonne montant : ' . $colMontant;
                                        $found = true;
                                    } else {
                                        $valeur = 0;
                                        $detail = 'Colonne trouvée mais vide, valeur mise à 0 (' . $code . ', ' . $cat . ') dans le fichier ' . $sourceType;
                                        $found = true;
                                    }
                                    break;
                                }
                            }
                            if ($found) break;
                        }
                        if (!$found) {
                            $detail = 'Code rubrique non trouvé ou montant absent dans les fichiers (' . $code . ', ' . $cat . ')';
                            $html .= '<tr style="background:#ffe0e0"><td>' . htmlspecialchars($code) . '</td><td>' . htmlspecialchars($cat) . '</td><td>' . htmlspecialchars($nom) . '</td><td><b>Non trouvé</b></td><td>' . htmlspecialchars($detail ?: 'Aucune correspondance') . '</td></tr>';
                        } else {
                            $html .= '<tr><td>' . htmlspecialchars($code) . '</td><td>' . htmlspecialchars($cat) . '</td><td>' . htmlspecialchars($nom) . '</td><td>' . htmlspecialchars($valeur) . '</td><td>' . htmlspecialchars($detail) . '</td></tr>';
                        }
                    }
                    $html .= '</table>';
                }
                $message = ['success', $html];

            } else {
                $message = ['error', implode('<br>', $errors)];
            }
            }


            return $this->render('moulinette/index.html.twig', [
                'agencies' => $agencies,
                'message' => $message,
                'selectedAgency' => $selectedAgency,
                'agencyRubrics' => $agencyRubrics,
            ]);
    }
}
