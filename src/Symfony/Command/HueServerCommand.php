<?php

namespace OguzhanTogay\HueClient\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use OguzhanTogay\HueClient\HueClient;

#[AsCommand(
    name: 'hue:serve',
    description: 'Start the Hue REST API server'
)]
class HueServerCommand extends Command
{
    public function __construct(private HueClient $hueClient)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'port',
            'p',
            InputOption::VALUE_REQUIRED,
            'Server port',
            8080
        );
        
        $this->addOption(
            'host',
            'H',
            InputOption::VALUE_REQUIRED,
            'Server host',
            'localhost'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $port = $input->getOption('port');
        $host = $input->getOption('host');

        try {
            if (!$this->hueClient->isConnected()) {
                $io->error('❌ Cannot connect to Hue Bridge');
                $io->note('Run: bin/console hue:setup');
                return Command::FAILURE;
            }

            $io->title('🚀 Starting Hue REST API Server');
            
            $io->definitionList(
                ['Bridge' => $this->hueClient->getBridgeIp()],
                ['Server' => "http://{$host}:{$port}"],
                ['Documentation' => "http://{$host}:{$port}/docs"],
                ['API Base' => "http://{$host}:{$port}/api"]
            );
            
            $io->info('✅ Server running... Press Ctrl+C to stop');
            
            // Set environment variables
            putenv("HUE_BRIDGE_IP={$this->hueClient->getBridgeIp()}");
            putenv("HUE_USERNAME={$this->hueClient->getUsername()}");

            // Start built-in PHP server
            $publicPath = dirname(__DIR__, 3) . '/public';
            $router = $publicPath . '/api.php';
            
            $command = "php -S {$host}:{$port} -t {$publicPath} {$router}";
            passthru($command);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("❌ Server failed to start: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}