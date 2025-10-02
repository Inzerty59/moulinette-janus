<?php

namespace App\Controller\Admin;

use App\Entity\Agency;
use App\Entity\AgencyRubric;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use Symfony\Component\Validator\Constraints\Date;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;

#[IsGranted('ROLE_USER')]
class AgencyCrudController extends AbstractCrudController
{
    private ManagerRegistry $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    public static function getEntityFqcn(): string
    {
        return Agency::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Agence')
            ->setEntityLabelInPlural('Agences')
            ->setPageTitle('new', 'Créer une nouvelle agence')
            ->setPageTitle('edit', 'Modifier l\'agence')
            ->setPageTitle('index', 'Liste des agences')
            ->setPageTitle('detail', 'Détails de l\'agence');
    }

    public function configureActions(Actions $actions): Actions
    {
        $duplicateAction = Action::new('duplicate', 'Dupliquer')
            ->linkToRoute('admin_agency_duplicate', function (Agency $agency) {
                return ['id' => $agency->getId()];
            })
            ->displayAsLink();

        if (!$this->isGranted('ROLE_ADMIN')) {
            $actions->disable(Action::DELETE);
        }
        $actions->add(Crud::PAGE_INDEX, $duplicateAction);

        return $actions;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
                IdField::new('id')->hideOnForm()->hideOnIndex()->hideOnDetail(),
                TextField::new('code', 'Code'),
                TextField::new('name', 'Nom')->setTemplatePath('admin/field/agency_name_link.html.twig'),
                TextField::new('timezone', 'Fuseau horaire')->hideOnForm()->hideOnIndex()->hideOnDetail(),
                DateTimeField::new('createdAt', 'Créée le')->hideOnForm()->hideOnIndex()->hideOnDetail(),
                CollectionField::new('agencyRubrics', 'Rubriques')
                ->setEntryIsComplex(true)
                ->setTemplatePath('admin/agency_rubrics_embedded.html.twig')
                ->onlyOnDetail(),
        ];
    }

    public function detail(\EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext $context)
    {
        $agency = $context->getEntity()->getInstance();
        if ($agency) {
            $this->doctrine->getManager()->refresh($agency);
        }
        return parent::detail($context);
    }

    #[Route('/admin/agency/duplicate/{id}', name: 'admin_agency_duplicate')]
    public function duplicateAgency(int $id, Request $request, AdminUrlGenerator $adminUrlGenerator): Response
    {
        $em = $this->doctrine->getManager();
        $originalAgency = $em->getRepository(Agency::class)->find($id);
        
        if (!$originalAgency) {
            $this->addFlash('error', 'Agence introuvable.');
            return $this->redirectToIndex($adminUrlGenerator);
        }

        if ($request->isMethod('POST')) {
            return $this->handleDuplication($request, $originalAgency, $em, $adminUrlGenerator);
        }
        
        return $this->renderDuplicationForm($originalAgency);
    }

    private function redirectToIndex(AdminUrlGenerator $adminUrlGenerator): RedirectResponse
    {
        $url = $adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl();
            
        return new RedirectResponse($url);
    }

    private function renderDuplicationForm(Agency $originalAgency): Response
    {
        return $this->render('admin/agency_duplicate.html.twig', [
            'originalAgency' => $originalAgency,
            'suggestedCode' => $originalAgency->getCode() . '_COPIE',
            'suggestedName' => $originalAgency->getName() . ' (Copie)',
        ]);
    }

    private function handleDuplication(
        Request $request, 
        Agency $originalAgency, 
        $em, 
        AdminUrlGenerator $adminUrlGenerator
    ): Response {
        $code = trim((string) $request->request->get('code', ''));
        $name = trim((string) $request->request->get('name', ''));
        
        if (empty($code) || empty($name)) {
            $this->addFlash('error', 'Le code et le nom sont obligatoires.');
            return $this->renderDuplicationForm($originalAgency);
        }
        
        // Vérifier l'unicité du code
        $existingAgency = $em->getRepository(Agency::class)->findOneBy(['code' => $code]);
        if ($existingAgency) {
            $this->addFlash('error', 'Une agence avec ce code existe déjà.');
            return $this->renderDuplicationForm($originalAgency);
        }
        
        try {
            $em->beginTransaction();
            
            $newAgency = $this->createAgencyCopy($originalAgency, $code, $name);
            $em->persist($newAgency);
            $em->flush();
            
            $this->duplicateAgencyRubrics($originalAgency, $newAgency, $em);
            $em->flush();
            
            $em->commit();
        } catch (\Exception $e) {
            $em->rollback();
            $this->addFlash('error', 'Une erreur est survenue lors de la duplication.');
            return $this->renderDuplicationForm($originalAgency);
        }
        
        return $this->redirectToIndex($adminUrlGenerator);
    }

    private function createAgencyCopy(Agency $originalAgency, string $code, string $name): Agency
    {
        $newAgency = new Agency();
        $newAgency->setCode($code);
        $newAgency->setName($name);
        $newAgency->setActive($originalAgency->isActive());
        $newAgency->setTimezone($originalAgency->getTimezone());
        $newAgency->setTotalsPolicy($originalAgency->getTotalsPolicy());
        $newAgency->setSettings($originalAgency->getSettings());
        
        return $newAgency;
    }

    private function duplicateAgencyRubrics(Agency $originalAgency, Agency $newAgency, $em): void
    {
        foreach ($originalAgency->getAgencyRubrics() as $originalRubric) {
            $newRubric = new AgencyRubric();
            $newRubric->setAgency($newAgency);
            $newRubric->setCode($originalRubric->getCode());
            $newRubric->setName($originalRubric->getName());
            $newRubric->setCategory($originalRubric->getCategory());
            
            $em->persist($newRubric);
        }
    }
}
