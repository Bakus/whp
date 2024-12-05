<?php

namespace App\Controller;

use App\Entity\IpAddress;
use App\Service\OsFunctionsService;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, BooleanField, ChoiceField, IdField, TextField};

/**
 * @todo: Add validation to IP Address field using Symfony\Component\Validator\Constraints\Ip;
 */
class IpAddressCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return IpAddress::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setSearchFields(['ip_address'])
            ->setDefaultSort(['ip_address' => 'ASC'])
            ->setAutofocusSearch();
    }

    public function configureFields(string $pageName): iterable
    {
        $osFunctions = new OsFunctionsService();
        $phpVersionsInstalled = $osFunctions->getPhpVersionsInstalled();
        foreach ($phpVersionsInstalled as $version) {
            $phpVersions['PHP ' . $version] = $version;
        }

        return [
            IdField::new('id')
                ->onlyOnDetail(),
            TextField::new('ip_address'),
            TextField::new('webroot')
                ->setEmptyData('public_html'),
            AssociationField::new('ssl_cert', 'SSL Certificate')
                ->setHelp('SSL Certificate associated with this IP Address. Used when client don\'t send SNI'),
            BooleanField::new('redirect_to_ssl', 'Always redirect to SSL'),
            AssociationField::new('owner', 'User'),
            ChoiceField::new('php_version')
                ->setChoices($phpVersions)
                ->setHelp('PHP version for this domain.'),
            BooleanField::new('is_active'),
            AssociationField::new('domains', 'Domains')
                ->onlyOnDetail(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
