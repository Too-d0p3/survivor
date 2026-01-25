<?php

namespace App\Cli\User;

use App\Shared\SampleData\SampleData;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:sample-data:create',
    description: 'Create sample data in configured order'
)]
class CreateSampleDataCommand extends Command
{

    /**
     * @param iterable<SampleData> $sampleDataCreators
     */
    public function __construct(
        #[AutowireIterator('app.shared.sample_data')]
        private readonly iterable $sampleDataCreators,

        #[Autowire('%app_sample_data%')]
        private readonly array $sampleDataConfig,

        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'reset-db',
                null,
                InputOption::VALUE_NONE,
                'Drops and recreates the database before creating sample data'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('reset-db')) {
            $this->resetDatabasePostgres($io);
            $this->runMigrations($output);
        }

        $order = $this->sampleDataConfig['order'] ?? [];

        $map = [];
        foreach ($this->sampleDataCreators as $sampleDataCreator) {
            $map[$sampleDataCreator::class] = $sampleDataCreator;
        }

        foreach ($order as $class) {
            if (!isset($map[$class])) {
                throw new \RuntimeException(sprintf(
                    'SampleData class `%s` is not registered or does not implement SampleData interface.',
                    $class
                ));
            }


            $output->writeln(sprintf('▶ %s', $class));
            $map[$class]->create();
        }


        $output->writeln('<info>✔ Sample data created</info>');
        return Command::SUCCESS;
    }

    private function resetDatabasePostgres(SymfonyStyle $io): void
    {
        if (($_ENV['APP_ENV'] ?? 'dev') === 'prod') {
            throw new \RuntimeException('Database reset is not allowed in production.');
        }


        $params = $this->connection->getParams();
        $dbName = $params['dbname'] ?? null;


        if (!$dbName) {
            throw new \RuntimeException('Database name not found in connection params.');
        }


        if (!$io->confirm(
            sprintf('⚠ This will DROP and recreate database "%s". Continue?', $dbName),
            false
        )) {
            $io->warning('Database reset aborted.');
            return;
        }


// 1) Připoj se na "postgres" (ne na cílovou DB)
        $adminParams = $params;
        $adminParams['dbname'] = $params['default_dbname'] ?? 'postgres';


// aby ses nezacyklil na stejnou connection instanci
        $adminConn = DriverManager::getConnection($adminParams);


// 2) Ukonči všechny ostatní sessions do cílové DB
// (bez toho drop často neprojde)
        $adminConn->executeStatement(
            "SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE datname = :db
AND pid <> pg_backend_pid()",
            ['db' => $dbName]
        );


// 3) DROP + CREATE (identifikátor musí být správně quotovaný)
        $platform = $adminConn->getDatabasePlatform();
        $quotedDb = $platform->quoteSingleIdentifier($dbName);


        try {
            $adminConn->executeStatement("DROP DATABASE IF EXISTS {$quotedDb}");
            $adminConn->executeStatement("CREATE DATABASE {$quotedDb}");
        } catch (Exception $e) {
            throw new \RuntimeException('Failed to reset database: '.$e->getMessage(), 0, $e);
        } finally {
            $adminConn->close();
        }


        $io->success(sprintf('Database "%s" recreated.', $dbName));
    }

    private function runMigrations(OutputInterface $output): void
    {
        $application = $this->getApplication();
        if (!$application) {
            throw new \RuntimeException('Console application not available.');
        }


        $command = $application->find('doctrine:migrations:migrate');


        $input = new ArrayInput([
            '--no-interaction' => true,
        ]);


// nech to psát do stejného outputu, ať vidíš co dělá
        $command->run($input, $output);
    }

}