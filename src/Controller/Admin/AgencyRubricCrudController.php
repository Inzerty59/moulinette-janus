<?php

namespace App\Controller\Admin;

use App\Entity\AgencyRubric;
use App\Entity\Agency;
use App\Controller\Admin\AgencyCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Component\Validator\Constraints\Date;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Doctrine\ORM\EntityManagerInterface;

class AgencyRubricCrudController extends AbstractCrudController
{
    private AdminUrlGenerator $adminUrlGenerator;
    private ManagerRegistry $doctrine;

    public function __construct(AdminUrlGenerator $adminUrlGenerator, ManagerRegistry $doctrine)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->doctrine = $doctrine;
    }

    public static function getEntityFqcn(): string
    {
        return AgencyRubric::class;
    }
    
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Rubrique d\'agence')
            ->setEntityLabelInPlural('Rubriques d\'agence')
            ->setPageTitle('index', 'Rubriques d\'agence')
            ->setPageTitle('new', 'Créer une rubrique')
            ->setPageTitle('edit', 'Modifier la rubrique')
            ->setPageTitle('detail', 'Rubrique');
    }
    
    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('code')->setLabel('Code'),
            TextField::new('name')->setLabel('Nom'),
            TextField::new('category')->setLabel('Catégorie'),
            AssociationField::new('agency')->setLabel('Agence')->hideOnForm(),
            DateTimeField::new('createdAt')->setLabel('Créé le')->hideOnForm()->hideOnIndex()->hideOnDetail(),
            DateTimeField::new('updatedAt')->setLabel('Modifié le')->hideOnForm()->hideOnIndex()->hideOnDetail(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->update(Crud::PAGE_DETAIL, Action::INDEX, function (Action $action) {
                return $action->linkToUrl(function (AgencyRubric $entity) {
                    return $this->adminUrlGenerator
                        ->setController(AgencyCrudController::class)
                        ->setAction(Action::DETAIL)
                        ->setEntityId($entity->getAgency()->getId())
                        ->generateUrl();
                });
            });
    }

    public function createEntity(string $entityFqcn)
    {
        $entity = new AgencyRubric();
        
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $agencyId = $request->query->get('agency');
        
        if ($agencyId) {
            $agencyRepository = $this->doctrine->getRepository(Agency::class);
            $agency = $agencyRepository->find($agencyId);
            if ($agency) {
                $entity->setAgency($agency);
            }
        }
        
        return $entity;
    }

    public function new(AdminContext $context)
    {
        $response = parent::new($context);
        
        if ($response instanceof RedirectResponse) {
            $entity = $context->getEntity()->getInstance();
            if ($entity && $entity->getId()) {
                $agencyId = $entity->getAgency()->getId();
                
                $redirectUrl = $this->adminUrlGenerator
                    ->setController(AgencyCrudController::class)
                    ->setAction(Action::DETAIL)
                    ->setEntityId($agencyId)
                    ->generateUrl();
                    
                return new RedirectResponse($redirectUrl);
            }
        }
        
        return $response;
    }

    public function edit(AdminContext $context)
    {
        $response = parent::edit($context);
        
        if ($response instanceof RedirectResponse) {
            $entity = $context->getEntity()->getInstance();
            if ($entity) {
                $agencyId = $entity->getAgency()->getId();
                
                $redirectUrl = $this->adminUrlGenerator
                    ->setController(AgencyCrudController::class)
                    ->setAction(Action::DETAIL)
                    ->setEntityId($agencyId)
                    ->generateUrl();
                    
                return new RedirectResponse($redirectUrl);
            }
        }
        
        return $response;
    }

    public function delete(AdminContext $context)
    {
        $entity = $context->getEntity()->getInstance();
        $agencyId = $entity->getAgency()->getId();
        
        $response = parent::delete($context);
        
        if ($response instanceof RedirectResponse) {
            $redirectUrl = $this->adminUrlGenerator
                ->setController(AgencyCrudController::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($agencyId)
                ->generateUrl();
                
            return new RedirectResponse($redirectUrl);
        }
        
        return $response;
    }

}