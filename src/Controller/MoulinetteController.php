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
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-excel',
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
                    $rubriqueHeader = isset($rubriqueRows[0]) ? array_map('strtolower', $rubriqueRows[0]) : [];
                    $isJALCOT = in_array('mont_base', $rubriqueHeader);
                    $normalize = function($str) {
                        $str = strtolower($str);
                        $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
                        $str = preg_replace('/[^a-z0-9 ]/i', '', $str);
                        $str = preg_replace('/[\s\p{Zs}]+/u', ' ', $str);
                        $str = trim($str);
                        return $str;
                    };
                    
                    $valeursParCode = [];
                    
                    $rubriquesPar0 = ['1175', '1201', '4072', '4076', '5101', '5103'];
                    foreach ($agencyRubrics as $rubric) {
                        $code = trim($rubric->getCode());
                        $cat = trim($rubric->getCategory());
                        $nom = trim($rubric->getName());
                        $valeur = '';
                        $detail = '';
                        $catNorm = $normalize($cat);
                        $found = false;
                        
                        if ($code === 'Agence' && $catNorm === 'patronal montant') {
                            $totalCharges = 0;
                            foreach ($cotisationRows as $rowIdx => $row) {
                                if ($rowIdx === 0) continue;
                                if (isset($row[8]) && is_numeric($row[8])) {
                                    $totalCharges += floatval($row[8]);
                                }
                            }
                            $valeur = number_format($totalCharges, 2, ',', ' ');
                            $detail = 'Somme calculée des charges patronales du fichier cotisation';
                            $found = true;
                        } else {
                        $sources = [
                            [
                                'rows' => $cotisationRows,
                                'type' => 'cotisation',
                                'colMapping' => [
                                    'base' => 4,
                                    'salarie montant' => 6,
                                    'patronal montant' => 8,
                                    'total' => 10,
                                ]
                            ],
                            [
                                'rows' => $matriculeRows,
                                'type' => 'matricule',
                                'colMapping' => [
                                    'brut' => 4, // Brut total
                                    'brut total' => 4, // Brut total
                                    'tranche a' => 12, // Tranche A
                                    'tranche b' => 13, // Tranche B
                                    'heures travaillees' => 10, // Hrs Travaillées
                                    'net a payer' => 9, // Net Payer
                                ]
                            ],
                            [
                                'rows' => $rubriqueRows,
                                'type' => 'rubrique',
                                'colMapping' => [
                                    'base' => 6,
                                    'a payer' => 7,
                                    'a retenir' => 8,
                                    'salarie montant' => 7,
                                    'patronal montant' => 8,
                                    'total' => 11,
                                ]
                            ],
                        ];
                        
                        if ($catNorm === 'patronal montant') {
                            $sources = array_filter($sources, function($s) { return $s['type'] === 'cotisation'; }) + 
                                      array_filter($sources, function($s) { return $s['type'] !== 'cotisation'; });
                        } elseif ($catNorm === 'salarie montant') {
                            $sourcesOrdered = [];
                            foreach ($sources as $s) {
                                if ($s['type'] === 'rubrique') $sourcesOrdered[] = $s;
                            }
                            foreach ($sources as $s) {
                                if ($s['type'] === 'cotisation') $sourcesOrdered[] = $s;
                            }
                            foreach ($sources as $s) {
                                if ($s['type'] === 'matricule') $sourcesOrdered[] = $s;
                            }
                            $sources = $sourcesOrdered;
                        } elseif (in_array($catNorm, ['a payer', 'a retenir'])) {
                            $sources = array_filter($sources, function($s) { return $s['type'] === 'rubrique'; }) + 
                                      array_filter($sources, function($s) { return $s['type'] !== 'rubrique'; });
                        } elseif ($catNorm === 'base') {
                            $sourcesOrdered = [];
                            foreach ($sources as $s) {
                                if ($s['type'] === 'cotisation') $sourcesOrdered[] = $s;
                            }
                            foreach ($sources as $s) {
                                if ($s['type'] === 'rubrique') $sourcesOrdered[] = $s;
                            }
                            foreach ($sources as $s) {
                                if ($s['type'] === 'matricule') $sourcesOrdered[] = $s;
                            }
                            $sources = $sourcesOrdered;
                        }
                        foreach ($sources as $source) {
                            $rows = $source['rows'];
                            $sourceType = $source['type'];
                            $colMapping = $source['colMapping'];
                            
                            $colonnesAEssayer = [];
                            
                            if ($catNorm === 'base') {
                                if ($sourceType === 'cotisation' && isset($colMapping['base'])) {
                                    $colonnesAEssayer[] = $colMapping['base'];
                                } elseif ($sourceType === 'rubrique' && isset($colMapping['base'])) {
                                    $colonnesAEssayer[] = $colMapping['base'];
                                }
                            } elseif ($catNorm === 'patronal montant') {
                                if ($sourceType === 'cotisation' && isset($colMapping['patronal montant'])) {
                                    $colonnesAEssayer[] = $colMapping['patronal montant'];
                                }
                            } elseif ($catNorm === 'salarie montant') {
                                if ($sourceType === 'rubrique' && isset($colMapping['a retenir'])) {
                                    $colonnesAEssayer[] = $colMapping['a retenir']; // Pour 5110 salarié montant
                                } elseif ($sourceType === 'cotisation' && isset($colMapping['salarie montant'])) {
                                    $colonnesAEssayer[] = $colMapping['salarie montant'];
                                }
                            } elseif ($catNorm === 'a payer') {
                                if ($sourceType === 'rubrique' && isset($colMapping['a payer'])) {
                                    $colonnesAEssayer[] = $colMapping['a payer'];
                                }
                            } elseif ($catNorm === 'a retenir') {
                                if ($sourceType === 'rubrique' && isset($colMapping['a retenir'])) {
                                    $colonnesAEssayer[] = $colMapping['a retenir'];
                                }
                            }
                            
                            if (empty($colonnesAEssayer) && isset($colMapping[$catNorm])) {
                                $colonnesAEssayer[] = $colMapping[$catNorm];
                            }
                            
                            if (empty($colonnesAEssayer)) continue;
                            
                            $matchCount = 0;
                            foreach ($rows as $rowIdx => $row) {
                                if ($rowIdx === 0) continue;
                                
                                $searchMatch = false;
                                if ($code === 'total' && $catNorm === 'a payer' && $sourceType === 'rubrique') {
                                    if (isset($row[2])) {
                                        $rowName = strtolower($row[2]);
                                        $searchName = strtolower($nom);
                                        if (stripos($rowName, 'brut') !== false && stripos($searchName, 'brut') !== false) {
                                            $searchMatch = true;
                                        } elseif (stripos($rowName, 'fiscal') !== false && stripos($searchName, 'fiscal') !== false) {
                                            $searchMatch = true;
                                        } elseif (stripos($rowName, 'net') !== false && stripos($searchName, 'net') !== false) {
                                            if (stripos($rowName, 'net (1)') === false && stripos($rowName, 'net    ***') !== false) {
                                                $searchMatch = true;
                                            }
                                        }
                                    }
                                } else {
                                    if (isset($row[1]) && $normalize($row[1]) === $normalize($code)) {
                                        $searchMatch = true;
                                    }
                                }
                                
                                if ($searchMatch) {
                                    $matchCount++;
                                    
                                    $valeurTrouvee = false;
                                    foreach ($colonnesAEssayer as $colMontant) {
                                        if ($code === '3031' && $matchCount === 2) {
                                            if (isset($row[$colMontant]) && $row[$colMontant] !== '' && $row[$colMontant] !== null) {
                                                $valeur = $rubric->formatValue($row[$colMontant]);
                                                $detail = 'Correspondance sur code (' . $code . ') et catégorie (' . $cat . ') dans le fichier ' . $sourceType . ' (2ème occurrence) en colonne montant : ' . $colMontant;
                                                $found = true;
                                                $valeurTrouvee = true;
                                                break;
                                            }
                                        }
                                        if ($code !== '3031' && $matchCount === 1) {
                                            if (isset($row[$colMontant]) && $row[$colMontant] !== '' && $row[$colMontant] !== null) {
                                                $valeur = $rubric->formatValue($row[$colMontant]);
                                                $detail = 'Correspondance sur code (' . $code . ') et catégorie (' . $cat . ') dans le fichier ' . $sourceType . ' en colonne montant : ' . $colMontant;
                                                $found = true;
                                                $valeurTrouvee = true;
                                                break;
                                            }
                                        }
                                    }
                                    
                                    if (!$valeurTrouvee) {
                                        if ($code === '3031' && $matchCount === 2) {
                                            $valeur = '0';
                                            $detail = 'Rubrique trouvée mais toutes colonnes vides, valeur mise à 0 (' . $code . ', ' . $cat . ') dans le fichier ' . $sourceType . ' (2ème occurrence)';
                                            $found = true;
                                        }
                                        if ($code !== '3031' && $matchCount === 1) {
                                            $valeur = '0';
                                            $detail = 'Rubrique trouvée mais toutes colonnes vides, valeur mise à 0 (' . $code . ', ' . $cat . ') dans le fichier ' . $sourceType;
                                            $found = true;
                                        }
                                    }
                                    
                                    if ($found) break;
                                }
                            }
                            if ($found) break;
                        }
                        }
                        
                        if (!$found) {
                            if (in_array($code, $rubriquesPar0)) {
                                $valeur = '0';
                                $detail = 'Rubrique définie par défaut à 0 (' . $code . ', ' . $cat . ')';
                            } else {
                                $valeur = '0';
                                $detail = 'Code rubrique non trouvé ou montant absent dans les fichiers (' . $code . ', ' . $cat . '), valeur mise à 0';
                            }
                        }

                        $cleComposite = $code . '_' . $catNorm;
                        
                        if ($code === 'total' && $catNorm === 'a payer') {
                            $nomNormalise = strtolower($nom);
                            if (stripos($nomNormalise, 'brut') !== false) {
                                $cleComposite = 'total_brut_a_payer';
                            } elseif (stripos($nomNormalise, 'fiscal') !== false) {
                                $cleComposite = 'total_fiscal';
                            } elseif (stripos($nomNormalise, 'net') !== false) {
                                $cleComposite = 'total_net_a_payer';
                            }
                        }
                        
                        $valeursParCode[$cleComposite] = $valeur;
                    }
                    
                    $outputSpreadsheet = IOFactory::load($_FILES['output_file']['tmp_name']);
                    $outputSheet = $outputSpreadsheet->getActiveSheet();
                    
                    $maxRow = $outputSheet->getHighestRow();
                    
                    $valeursEcrites = 0;
                    for ($row = 1; $row <= $maxRow; $row++) {
                        $cellValue = $outputSheet->getCell([1, $row])->getValue();
                        
                        $cellValueB = '';
                        try {
                            $cellValueB = $outputSheet->getCell([2, $row])->getValue();
                        } catch (\Exception $e) {
                            $cellValueB = '';
                        }
                        
                        if ($cellValue) {
                            $cellValue = trim($cellValue);
                            $cellValueB = trim($cellValueB);
                            $codeRubrique = null;
                            
                            $cellValueComplete = $cellValue . ' ' . $cellValueB;
                            
                            $matches = [];
                            $codeRubrique = null;
                            $categorieDeduiteFromExcel = null;
                            
                            if (stripos($cellValueComplete, 'agence') !== false && stripos($cellValueComplete, 'patronal') !== false) {
                                $codeRubrique = 'agence';
                                $categorieDeduiteFromExcel = 'patronal montant';
                            }
                            elseif (stripos($cellValueComplete, 'brut total') !== false) {
                                $codeRubrique = 'total';
                                $categorieDeduiteFromExcel = 'brut';
                            }
                            elseif (stripos($cellValueComplete, 'brut tranche a') !== false) {
                                $codeRubrique = 'total';
                                $categorieDeduiteFromExcel = 'tranche a';
                            }
                            elseif (stripos($cellValueComplete, 'brut tranche b') !== false) {
                                $codeRubrique = 'total';
                                $categorieDeduiteFromExcel = 'tranche b';
                            }
                            elseif (stripos($cellValueComplete, 'heures travaillées') !== false) {
                                $codeRubrique = 'total';
                                $categorieDeduiteFromExcel = 'heures travaillees';
                            }
                            elseif (stripos($cellValueComplete, 'net à payer') !== false || stripos($cellValueComplete, 'net a payer') !== false) {
                                $codeRubrique = 'total';
                                $categorieDeduiteFromExcel = 'net a payer';
                            }
                            elseif (stripos($cellValueComplete, 'total') !== false && stripos($cellValueComplete, 'brut') !== false && stripos($cellValueComplete, 'payer') !== false) {
                                $codeRubrique = 'total';
                                $categorieDeduiteFromExcel = 'brut_a_payer';
                            }
                            elseif (stripos($cellValueComplete, 'total') !== false && stripos($cellValueComplete, 'fiscal') !== false) {
                                $codeRubrique = 'total';
                                $categorieDeduiteFromExcel = 'fiscal';
                            }
                            elseif (stripos($cellValueComplete, 'total') !== false && stripos($cellValueComplete, 'net') !== false) {
                                $codeRubrique = 'total';
                                $categorieDeduiteFromExcel = 'net_a_payer';
                            }
                            elseif (stripos($cellValueComplete, 'total') !== false) {
                                $codeRubrique = 'total';
                                $categorieDeduiteFromExcel = 'a payer';
                            }
                            elseif (preg_match('/^(\d+)\s*(.*?)$/', $cellValue, $matches)) {
                                $codeRubrique = $matches[1];
                                $texteApresCode = trim($matches[2]);
                                
                                if (stripos($texteApresCode, 'patronal montant') !== false) {
                                    $categorieDeduiteFromExcel = 'patronal montant';
                                } elseif (stripos($texteApresCode, 'salarié montant') !== false || stripos($texteApresCode, 'salarie montant') !== false) {
                                    $categorieDeduiteFromExcel = 'salarie montant';
                                } elseif (stripos($texteApresCode, 'à payer') !== false || stripos($texteApresCode, 'a payer') !== false) {
                                    $categorieDeduiteFromExcel = 'a payer';
                                } elseif (stripos($texteApresCode, 'à retenir') !== false || stripos($texteApresCode, 'a retenir') !== false) {
                                    $categorieDeduiteFromExcel = 'a retenir';
                                } else {
                                    $categorieDeduiteFromExcel = 'base';
                                }
                            }
                            
                            if ($codeRubrique && $categorieDeduiteFromExcel) {
                                $cleComposite = $codeRubrique . '_' . $categorieDeduiteFromExcel;
                                
                                if (isset($valeursParCode[$cleComposite])) {
                                    $valeurPourExcel = $valeursParCode[$cleComposite];
                                    
                                    $valeurPourExcel = str_replace([' ', ','], ['', '.'], $valeurPourExcel);
                                    if (is_numeric($valeurPourExcel)) {
                                        $valeurPourExcel = floatval($valeurPourExcel);
                                    }
                                    
                                    $outputSheet->getCell([3, $row])->setValue($valeurPourExcel);
                                    $valeursEcrites++;
                                }
                            }
                        }
                    }
                    
                    $writer = IOFactory::createWriter($outputSpreadsheet, 'Xlsx');
                    $tempFile = tempnam(sys_get_temp_dir(), 'output_') . '.xlsx';
                    $writer->save($tempFile);
                    
                    $originalFileName = $_FILES['output_file']['name'];
                    
                    $response = new Response(file_get_contents($tempFile));
                    $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                    $response->headers->set('Content-Disposition', 'attachment; filename="' . $originalFileName . '"');
                    
                    unlink($tempFile);
                    
                    return $response;
                } else {
                    $message = ['error', 'Aucune agence sélectionnée ou aucune rubrique trouvée.'];
                }

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
