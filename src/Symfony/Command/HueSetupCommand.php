<?php

namespace OguzhanTogay\HueClient\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Discovery\BridgeDiscovery;

#[AsCommand(
    name: 'hue:setup',
    description: 'Setup and authenticate with Philips Hue bridge'
)]
class HueSetupCommand extends Command
{
    public function __construct(private BridgeDiscovery $discovery)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'ip',
            'i',
            InputOption::VALUE_REQUIRED,
            'Bridge IP address'
        );

        $this->addOption(
            'discover',
            'd',
            InputOption::VALUE_NONE,
            'Auto-discover bridge'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🔗 Philips Hue Bridge Setup');

        $bridgeIp = $input->getOption('ip');
        $discover = $input->getOption('discover');

        // Auto-discover if no IP provided or discover flag set
        if (!$bridgeIp || $discover) {
            $io->text('🔍 Discovering bridges...');
            $bridges = $this->discovery->discover();

            if (empty($bridges)) {
                $io->error('❌ No bridges found');
                return Command::FAILURE;
            }

            $bridgeIp = $bridges[0]->getIp();
            $io->info("✅ Found bridge at: {$bridgeIp}");
        }

        if (!$bridgeIp) {
            $io->error('❌ No bridge IP provided');
            $io->note('Use --ip option or --discover flag');
            return Command::FAILURE;
        }

        try {
            $io->text('🔗 Connecting to bridge...');
            $client = new HueClient($bridgeIp);

            $io->warning('⚠️  Press the link button on your Hue Bridge now!');

            if (!$io->confirm('Press Enter when you have pressed the link button', false)) {
                $io->info('Setup cancelled');
                return Command::FAILURE;
            }

            $io->text('🔐 Registering application...');
            $username = $client->register('symfony-hue-app', 'symfony-' . gethostname());

            $io->success('✅ Successfully registered with bridge!');

            $io->section('📝 Configuration');
            $io->text('Add these to your .env file:');
            $io->listing([
                "HUE_BRIDGE_IP={$bridgeIp}",
                "HUE_USERNAME={$username}"
            ]);

            $io->note('🎉 Setup complete! You can now use the Hue service in your Symfony app');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("❌ Setup failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
