<?php

namespace App\Controller\Admin;

use App\Entity\Agency;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use Symfony\Component\Validator\Constraints\Date;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
}
