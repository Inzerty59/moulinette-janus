<?php

namespace App\Controller;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MoulinetteController extends AbstractController
{
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

                if ($selectedAgency && $agencyRubrics && $rubriqueRows) {
                    $html = '<b>Correspondance rubriques agence (code, catégorie, nom, valeur trouvée) :</b><table border="1" cellpadding="3"><tr><th>Code</th><th>Catégorie</th><th>Nom</th><th>Valeur trouvée</th></tr>';
                    foreach ($agencyRubrics as $rubric) {
                        $code = trim($rubric->getCode());
                        $cat = trim($rubric->getCategory());
                        $nom = trim($rubric->getName());
                        $valeur = '';
                        $normalize = function($str) {
                            $str = strtolower($str);
                            $str = preg_replace('/[\s\p{Zs}]+/u', '', $str);
                            $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
                            return $str;
                        };
                        foreach ($rubriqueRows as $idx => $row) {
                            if (isset($row[1], $row[6])) {
                                $rowCode = trim((string)$row[1]);
                                if ($normalize($cat) === 'base') {
                                    if ($normalize($rowCode) === $normalize($code)) {
                                        $valeur = $row[6];
                                        $source = 'Rubrique (colonne Base, code seul)';
                                        $ligneTrouvee = $row;
                                        break;
                                    }
                                } else if (isset($row[2])) {
                                    $rowCat = trim((string)$row[2]);
                                    if (
                                        $normalize($rowCode) === $normalize($code) &&
                                        $normalize($rowCat) === $normalize($cat)
                                    ) {
                                        $valeur = $row[6];
                                        $source = 'Rubrique (colonne Base, code+catégorie)';
                                        $ligneTrouvee = $row;
                                        break;
                                    }
                                }
                            }
                        }
                        if ($valeur === '') {
                            $html .= '<tr style="background:#ffe0e0"><td>' . htmlspecialchars($code) . '</td><td>' . htmlspecialchars($cat) . '</td><td>' . htmlspecialchars($nom) . '</td><td></td></tr>';
                        } else {
                            $html .= '<tr><td>' . htmlspecialchars($code) . '</td><td>' . htmlspecialchars($cat) . '</td><td>' . htmlspecialchars($nom) . '</td><td>' . htmlspecialchars($valeur) . '</td></tr>';
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
