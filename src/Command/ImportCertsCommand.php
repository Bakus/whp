<?php

namespace App\Command;

use App\Entity\SslCert;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:import-certs',
    description: 'Import SSL Certificates into the database. Existing certificates will be skipped.',
)]
class ImportCertsCommand extends Command
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected UserPasswordHasherInterface $userPasswordHasher
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('certs-direrctory', InputArgument::REQUIRED, 'Path to the directory containing the certificates.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $certsDirectory = $input->getArgument('certs-direrctory');

        if (!is_dir($certsDirectory)) {
            $io->error('Directory ' . $certsDirectory . ' does not exist.');
            return Command::FAILURE;
        }

        $certsDirectory = rtrim($certsDirectory, '/');
        $io->info('Using ' . $certsDirectory . ' as the directory for certificates.');

        $whatToDo = [
            1 => 'Override certs in database (can break dependencies!)',
            2 => 'Add new certs to database',
        ];
        $action = $io->choice('Override certs in database or just add new ones?', $whatToDo, multiSelect: false);

        if ($action === 'Override certs in database') {
            $io->info('Cleaning SSL Certificates in database...');
            $certs = $this->entityManager->getRepository(SslCert::class)->findAll();
            foreach ($certs as $cert) {
                $this->entityManager->remove($cert);
            }
            $this->entityManager->flush();
        }

        $io->info('Importing SSL Certificates...');
        $files = glob($certsDirectory . '/*.crt');
        foreach ($files as $file) {
            $certName = basename($file, '.crt');
            $sni = null;

            $certContent = file_get_contents($file);
            $certData = openssl_x509_parse($certContent);
            if (!isset($certData['subject']['CN'])) {
                $io->warning('Skipping ' . $certName . ' - missing CN in cert.');
                continue;
            }

            if (isset($certData['extensions']['subjectAltName'])) {
                $sni = $certData['extensions']['subjectAltName'];
                $sni = str_replace('DNS:', '', $sni);
                $sni = explode(',', $sni);
                $sni = array_map('trim', $sni);
                $sni = array_filter($sni);
                $sni = implode("\n", $sni);
            }

            if ($this->entityManager->getRepository(SslCert::class)->findOneBy(['crt_file' => $file])) {
                $io->info('Updating ' . $certName . ' info in database...');
                $cert = $this->entityManager->getRepository(SslCert::class)->findOneBy(['crt_file' => $file]);
                $cert->setName($certData['subject']['CN']);
                $cert->setSni($sni);
                $this->entityManager->persist($cert);
                continue;
            }

            $keyFile = $certsDirectory . '/' . $certName . '.key';
            if (!file_exists($keyFile)) {
                $io->warning('Skipping ' . $certName . ' - missing key file.');
                continue;
            }

            $caFile = $certsDirectory . '/' . $certName . '.ca';
            if (!file_exists($caFile)) {
                $io->warning('Skipping ' . $certName . ' - missing ca file.');
                continue;
            }

            $io->info('Importing ' . $certName . '...');
            $cert = new SslCert();
            $cert->setName($certData['subject']['CN']);
            $cert->setCrtFile($certsDirectory . '/' . $certName . '.crt');
            $cert->setKeyFile($certsDirectory . '/' . $certName . '.key');
            $cert->setCaFile($certsDirectory . '/' . $certName . '.ca');
            $this->entityManager->persist($cert);
        }
        $this->entityManager->flush();

        $io->success('Done!');

        return Command::SUCCESS;
    }
}
