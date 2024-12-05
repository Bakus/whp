<?php

namespace App\Controller;

use App\Entity\SslCert;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, BooleanField, IdField, TextField, TextareaField};

class SslCertCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SslCert::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setSearchFields(['name'])
            ->setDefaultSort(['name' => 'ASC'])
            ->setAutofocusSearch()
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->onlyOnDetail(),
            TextField::new('name'),
            TextField::new('crt_file'),
            TextField::new('key_file'),
            TextField::new('ca_file'),
            TextareaField::new('sni')
                //->hideOnIndex()
                ->setHelp('SNI domains for this certificate'),
            BooleanField::new('is_active'),
            AssociationField::new('domains', 'Domains')
                ->onlyOnDetail(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
        ;
    }
}
