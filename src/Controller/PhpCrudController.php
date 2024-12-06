<?php

namespace App\Controller;

use App\Entity\Php;
use App\Service\OsFunctionsService;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, ChoiceField, CodeEditorField, IntegerField, TextField};

class PhpCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Php::class;
    }

    public function configureFields(string $pageName): iterable
    {
        $osFunctions = new OsFunctionsService();
        $phpVersionsInstalled = $osFunctions->getPhpVersionsInstalled();
        foreach ($phpVersionsInstalled as $version) {
            $phpVersions['PHP ' . $version] = $version;
        }

        return [
            ChoiceField::new('version')
                ->setChoices($phpVersions)
                ->setHelp('PHP version to configure.'),
            AssociationField::new('user', 'User')
                ->setHelp('User to configure PHP for. If empty, configuration will be applied for default system user (www-data mostly).'),
            IntegerField::new('startServers')
                ->setHelp('Number of child processes created on startup.')
                ->setFormTypeOption('attr', ['min' => 1, 'max' => 20, 'step' => 1, 'value' => 2]),
            IntegerField::new('maxChildren')
                ->setHelp('Maximum number of child processes.')
                ->setFormTypeOption('attr', ['min' => 1, 'max' => 20, 'step' => 1, 'value' => 5]),
            IntegerField::new('minSpare')
                ->setHelp('Minimum number of idle child processes.')
                ->setFormTypeOption('attr', ['min' => 1, 'max' => 20, 'step' => 1, 'value' => 1]),
            IntegerField::new('maxSpare')
                ->setHelp('Maximum number of idle child processes.')
                ->setFormTypeOption('attr', ['min' => 1, 'max' => 20, 'step' => 1, 'value' => 3]),
            IntegerField::new('uploadSize')
                ->setHelp('Maximum upload size for PHP in MB.')
                ->setFormTypeOption('attr', ['min' => 2, 'max' => 2048, 'step' => 1, 'value' => 64]),
            TextField::new('errorLog')
                ->setHelp('Path to the error log file. NULL by default.')
                ->hideOnIndex(),
            TextField::new('slowLog')
                ->setHelp('Path to the slow log file. NULL by default.')
                ->hideOnIndex(),
            CodeEditorField::new('additionalConfig')
                ->setHelp('Additional PHP configuration.')
                ->hideOnIndex()
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
