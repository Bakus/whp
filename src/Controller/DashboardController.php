<?php

namespace App\Controller;

use App\Entity\{User, Domain, IpAddress, SslCert, DomainAlias, Php, SystemUser};
use App\Message\{ServiceRestart, RegenerateConfigs};
use App\Service\{ConfigGeneratorService, OsFunctionsService};

use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud, Dashboard, MenuItem, UserMenu};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

use Symfony\Component\HttpFoundation\{Response, HeaderUtils, RequestStack};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use Doctrine\ORM\EntityManagerInterface;
use Jfcherng\Diff\DiffHelper;

class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    #[Route('/', name: 'admin_dashboard')]
    public function index(): Response
    {
        $osFunctions = new OsFunctionsService();
        $servicesStatus = $osFunctions->getServicesStatus();

        $session = $this->requestStack->getSession();
        $sessionConfigFiles = $session->get('configFiles', null);

        return $this->render('dashboard.html.twig', [
            'servicesStatus' => $servicesStatus,
            'configFiles' => $sessionConfigFiles ?? $osFunctions->getConfigs(),
            'diffs_enabled' => ($sessionConfigFiles !== null),
        ]);
    }

    #[Route('/checkAllConfigFiles', name: 'checkAllConfigFiles')]
    public function indexFull(EntityManagerInterface $entityManager): Response
    {
        $osFunctions = new OsFunctionsService();
        $rendererName = 'SideBySide';
        $differOptions = [
            'context' => 32000,
            'lengthLimit' => 128000,
            'ignoreCase' => true,
            'ignoreLineEnding' => true,
            'ignoreWhitespace' => true,
        ];

        $configFiles = $osFunctions->getConfigs(true);
        $cgs = new ConfigGeneratorService($entityManager, $this->container->get('twig'), $osFunctions, new \App\Service\DnsService());
        $renderedFiles = $cgs->renderConfigFiles();
        $allFiles = [];
        $allFileNames = array_merge(array_keys($configFiles), array_keys($renderedFiles));
        $allFileNames = array_unique($allFileNames);
        sort($allFileNames);
        foreach ($allFileNames as $file) {
            $existing = $configFiles[$file]['content'] ?? '';
            $rendered = $renderedFiles[$file] ?? '';

            if ($existing === '') {
                $status = 'missing';
            }elseif ($rendered === '') {
                $status = 'for-deletion';
            }elseif ($existing === $rendered) {
                $status = 'ok';
            }else{
                $status = 'outdated';
            }

            $allFiles[$file] = [
                'size' => $configFiles[$file]['size'] ?? 0,
                'mtime' => $configFiles[$file]['mtime'] ?? null,
                'status' => $status,
                'diff' => ($status == 'outdated') ? DiffHelper::calculate($existing, $rendered, $rendererName, $differOptions) : "",
            ];
        }

        $session = $this->requestStack->getSession();
        $session->set('configFiles', $allFiles);
        $this->addFlash('info', 'Files generated in temporary session - results can be viewed below');
        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/restartService/{service}', name: 'restartService')]
    public function restartService($service, MessageBusInterface $bus): Response
    {
        $osFunctions = new OsFunctionsService();
        $managedServices = $osFunctions->getManagedServices();
        if (in_array($service, $managedServices)) {
            $bus->dispatch(new ServiceRestart($service));
            $this->addFlash('info', 'Service ' . str_replace('.service', '', $service) . ' restart requested');
        }else{
            $this->addFlash('error', 'Service ' . $service . ' is not managed!');
        }

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/regenerateConfigs', name: 'regenerateConfigs')]
    public function regenerateConfigs(MessageBusInterface $bus): Response
    {
        $bus->dispatch(new RegenerateConfigs());
        $this->addFlash('info', 'Config regeneration requested');
        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/downloadConfigFile', name: 'downloadConfigFile')]
    public function downloadConfigFile($file): Response
    {
        $osFunctions = new OsFunctionsService();

        $configFiles = $osFunctions->getConfigs();
        if (!array_key_exists($file, $configFiles)) {
            $this->addFlash('error', 'File not managed!');
            return $this->redirectToRoute('admin_dashboard');
        }

        $fileContent = $osFunctions->readConfig($file);

        $response = new Response($fileContent);
        $response->headers->set('Content-Type', 'text/plain');

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            basename($file)
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('IntraDom Network WebHosting Panel')
            ->setDefaultColorScheme('dark')
            ->renderContentMaximized()
            ->setFaviconPath('favicon.png');
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined()
        ;
    }

    public function configureMenuItems(): iterable
    {
        /**
         * @todo: Wait for update of EasyAdminBundle to fix problem with linkToDashboard
         * @see https://github.com/EasyCorp/EasyAdminBundle/issues/6523#issuecomment-2476259038
         * @see https://github.com/EasyCorp/EasyAdminBundle/issues/6550
         */
        // yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToUrl('Dashboard', 'fa fa-home',  $this->generateUrl('admin_dashboard'));

        yield MenuItem::section('Hosting configuration');
        yield MenuItem::linkToCrud('User', 'fas fa-user', User::class);
        yield MenuItem::linkToCrud('PHP', 'fas fa-globe', Php::class);
        yield MenuItem::linkToCrud('Domain', 'fas fa-globe', Domain::class);
        yield MenuItem::linkToCrud('IP Address', 'fas fa-network-wired', IpAddress::class);
        yield MenuItem::linkToCrud('SSL Cert', 'fas fa-lock', SslCert::class);
        yield MenuItem::linkToCrud('Domain Alias', 'fas fa-globe', DomainAlias::class);
        yield MenuItem::section('System');
        yield MenuItem::linkToCrud('Panel users', 'fas fa-user-secret', SystemUser::class);
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        /**
         * @var User|UserInterface $user
         */

        $url = $this->container->get(AdminUrlGenerator::class)
            ->setAction(Action::EDIT)
            ->setController(SystemUserCrudController::class)
            ->setEntityId($user->getId())
            ->generateUrl();
        return parent::configureUserMenu($user)
            ->addMenuItems([
                MenuItem::linkToUrl('Edit Profile', 'fa fa-id-card', $url),
            ]);
    }

    public function configureActions(): Actions
    {
        return parent::configureActions()
            ->update(Crud::PAGE_DETAIL, Action::INDEX, static function (Action $action) {
                return $action->setIcon('fa fa-list');
            })
            ->update(Crud::PAGE_DETAIL, Action::EDIT, static function (Action $action) {
                return $action->setIcon('fa fa-edit');
            })
            ;
    }
}
