<?php

namespace OguzhanTogay\HueClient\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use OguzhanTogay\HueClient\Discovery\BridgeDiscovery;

#[AsCommand(
    name: 'hue:discover',
    description: 'Discover Philips Hue bridges on the network'
)]
class HueDiscoverCommand extends Command
{
    public function __construct(private BridgeDiscovery $discovery)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'timeout',
            't',
            InputOption::VALUE_REQUIRED,
            'Discovery timeout in seconds',
            5
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('🔍 Philips Hue Bridge Discovery');
        
        $timeout = (int) $input->getOption('timeout');
        
        try {
            $io->text('Scanning network for Hue bridges...');
            $bridges = $this->discovery->discover($timeout);
            
            if (empty($bridges)) {
                $io->error('❌ No Hue bridges found on your network');
                $io->note('Make sure your bridge is connected and powered on');
                return Command::FAILURE;
            }

            $io->success("✅ Found " . count($bridges) . " bridge(s)");

            $rows = [];
            foreach ($bridges as $bridge) {
                $rows[] = [
                    $bridge->getId(),
                    $bridge->getIp(),
                    $bridge->getName() ?? 'Unknown',
                    $bridge->getModelId() ?? 'Unknown'
                ];
            }

            $io->table(['ID', 'IP Address', 'Name', 'Model'], $rows);
            
            $io->note('💡 To configure in Symfony:');
            $io->text([
                'Add to your .env file:',
                "HUE_BRIDGE_IP={$bridges[0]->getIp()}",
                'HUE_USERNAME=<run bin/console hue:setup to get username>'
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("❌ Discovery failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}