<?php

namespace OguzhanTogay\HueClient\Console;

use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Discovery\BridgeDiscovery;
use OguzhanTogay\HueClient\Effects\ColorLoop;
use OguzhanTogay\HueClient\Effects\Breathing;
use OguzhanTogay\HueClient\Effects\Alert;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class HueCommand extends Command
{
    protected static $defaultName = 'hue:command';
    private ?HueClient $client = null;
    private array $config = [];
    private string $configFile;

    public function __construct()
    {
        parent::__construct('hue:command');
        $this->configFile = $_SERVER['HOME'] . '/.hue-config.json';
        $this->loadConfig();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Philips Hue CLI tool')
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform')
            ->addArgument('target', InputArgument::OPTIONAL, 'Target light/group name or ID')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Apply to all lights')
            ->addOption('brightness', 'b', InputOption::VALUE_REQUIRED, 'Set brightness (0-100)')
            ->addOption('color', 'c', InputOption::VALUE_REQUIRED, 'Set color (hex)')
            ->addOption('temperature', 't', InputOption::VALUE_REQUIRED, 'Set color temperature (Kelvin)')
            ->addOption('duration', 'd', InputOption::VALUE_REQUIRED, 'Effect duration in seconds', '30')
            ->addOption('room', 'r', InputOption::VALUE_REQUIRED, 'Target room');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = $input->getArgument('action');

        try {
            switch ($action) {
                case 'discover':
                    return $this->discoverBridges($input, $output);
                case 'setup':
                    return $this->setupBridge($input, $output);
                case 'lights':
                    return $this->listLights($input, $output);
                case 'groups':
                case 'rooms':
                    return $this->listGroups($input, $output);
                case 'scenes':
                    return $this->listScenes($input, $output);
                case 'on':
                    return $this->turnOn($input, $output);
                case 'off':
                    return $this->turnOff($input, $output);
                case 'toggle':
                    return $this->toggle($input, $output);
                case 'brightness':
                    return $this->setBrightness($input, $output);
                case 'color':
                    return $this->setColor($input, $output);
                case 'scene':
                    return $this->handleScene($input, $output);
                case 'effect':
                    return $this->runEffect($input, $output);
                case 'interactive':
                    return $this->interactiveMode($input, $output);
                case 'server':
                    return $this->startServer($input, $output);
                case 'sensors':
                    return $this->listSensors($input, $output);
                case 'schedules':
                    return $this->listSchedules($input, $output);
                case 'status':
                    return $this->showStatus($input, $output);
                default:
                    $output->writeln("<error>Unknown action: {$action}</error>");
                    $this->showHelp($output);
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }

    private function discoverBridges(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Discovering Hue bridges...');

        $discovery = new BridgeDiscovery();
        $bridges = $discovery->discover();

        if (empty($bridges)) {
            $output->writeln('<error>No bridges found on your network</error>');
            return Command::FAILURE;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'IP Address', 'Name', 'Model']);

        foreach ($bridges as $bridge) {
            $table->addRow([
                $bridge->getId(),
                $bridge->getIp(),
                $bridge->getName() ?? 'Unknown',
                $bridge->getModelId() ?? 'Unknown'
            ]);
        }

        $table->render();
        return Command::SUCCESS;
    }

    private function setupBridge(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');

        // Discover bridges
        $output->writeln('Discovering bridges...');
        $discovery = new BridgeDiscovery();
        $bridges = $discovery->discover();

        if (empty($bridges)) {
            $output->writeln('<error>No bridges found. Please ensure your bridge is connected.</error>');
            return Command::FAILURE;
        }

        $bridge = $bridges[0];
        $output->writeln("Found bridge: {$bridge->getId()} at {$bridge->getIp()}");

        // Create client
        $client = new HueClient($bridge->getIp());

        // Register
        $question = new ConfirmationQuestion(
            'Press the link button on your Hue Bridge, then press Enter to continue: '
        );
        if (!$helper->ask($input, $output, $question)) {
            return Command::FAILURE;
        }

        try {
            $username = $client->register('hue-cli', gethostname());

            // Save config
            $this->config = [
                'bridge_ip' => $bridge->getIp(),
                'username' => $username
            ];
            $this->saveConfig();

            $output->writeln("<info>Successfully connected to bridge!</info>");
            $output->writeln("Username: {$username}");
            $output->writeln("Configuration saved to: {$this->configFile}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>Failed to register: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }

    private function listLights(InputInterface $input, OutputInterface $output): int
    {
        $client = $this->getClient();
        if (!$client) {
            $output->writeln('<error>Not configured. Run "hue setup" first.</error>');
            return Command::FAILURE;
        }

        $lights = $client->lights()->getAll();

        $table = new Table($output);
        $table->setHeaders(['ID', 'Name', 'Type', 'Status', 'Brightness']);

        foreach ($lights as $light) {
            $table->addRow([
                $light->getId(),
                $light->getName(),
                $light->getType(),
                $light->getState()->getStatus(),
                $light->getState()->isOn() ? $light->getState()->getBrightness() . '%' : 'N/A'
            ]);
        }

        $table->render();
        return Command::SUCCESS;
    }

    private function listGroups(InputInterface $input, OutputInterface $output): int
    {
        $client = $this->getClient();
        if (!$client) {
            $output->writeln('<error>Not configured. Run "hue setup" first.</error>');
            return Command::FAILURE;
        }

        $groups = $client->groups()->getAll();

        $table = new Table($output);
        $table->setHeaders(['ID', 'Name', 'Type', 'Lights', 'Any On']);

        foreach ($groups as $group) {
            if ($group->getId() === 0) {
                continue; // Skip "All lights" group
            }

            $table->addRow([
                $group->getId(),
                $group->getName(),
                $group->getType(),
                count($group->getLights()),
                $group->getState()->anyOn() ? 'Yes' : 'No'
            ]);
        }

        $table->render();
        return Command::SUCCESS;
    }

    private function listScenes(InputInterface $input, OutputInterface $output): int
    {
        $client = $this->getClient();
        if (!$client) {
            $output->writeln('<error>Not configured. Run "hue setup" first.</error>');
            return Command::FAILURE;
        }

        $scenes = $client->scenes()->getAll();

        $table = new Table($output);
        $table->setHeaders(['ID', 'Name', 'Type', 'Group', 'Lights']);

        foreach ($scenes as $scene) {
            $table->addRow([
                $scene->getId(),
                $scene->getName(),
                $scene->getType(),
                $scene->getGroup() ?? 'N/A',
                count($scene->getLights())
            ]);
        }

        $table->render();
        return Command::SUCCESS;
    }

    private function turnOn(InputInterface $input, OutputInterface $output): int
    {
        return $this->controlLights($input, $output, 'on');
    }

    private function turnOff(InputInterface $input, OutputInterface $output): int
    {
        return $this->controlLights($input, $output, 'off');
    }

    private function toggle(InputInterface $input, OutputInterface $output): int
    {
        return $this->controlLights($input, $output, 'toggle');
    }

    private function setBrightness(InputInterface $input, OutputInterface $output): int
    {
        $brightness = $input->getOption('brightness') ?? $input->getArgument('target');
        if (!$brightness || !is_numeric($brightness)) {
            $output->writeln('<error>Please specify brightness (0-100)</error>');
            return Command::FAILURE;
        }

        return $this->controlLights($input, $output, 'brightness', (int)$brightness);
    }

    private function setColor(InputInterface $input, OutputInterface $output): int
    {
        $color = $input->getOption('color') ?? $input->getArgument('target');
        if (!$color) {
            $output->writeln('<error>Please specify color (hex format)</error>');
            return Command::FAILURE;
        }

        return $this->controlLights($input, $output, 'color', $color);
    }

    private function controlLights(InputInterface $input, OutputInterface $output, string $action, $value = null): int
    {
        $client = $this->getClient();
        if (!$client) {
            $output->writeln('<error>Not configured. Run "hue setup" first.</error>');
            return Command::FAILURE;
        }

        $target = $input->getArgument('target');
        $all = $input->getOption('all');
        $room = $input->getOption('room');

        if ($all) {
            $group = $client->groups()->all();
            $this->executeAction($group, $action, $value);
            $output->writeln("<info>Applied {$action} to all lights</info>");
        } elseif ($room) {
            $group = $client->groups()->getByName($room);
            if (!$group) {
                $output->writeln("<error>Room '{$room}' not found</error>");
                return Command::FAILURE;
            }
            $this->executeAction($group, $action, $value);
            $output->writeln("<info>Applied {$action} to room '{$room}'</info>");
        } elseif ($target) {
            // Try as light first, then as group
            $light = is_numeric($target) ? $client->lights()->get((int)$target) : $client->lights()->getByName($target);

            if ($light) {
                $this->executeAction($light, $action, $value);
                $output->writeln("<info>Applied {$action} to light '{$light->getName()}'</info>");
            } else {
                $group = is_numeric($target)
                    ? $client->groups()->get((int)$target)
                    : $client->groups()->getByName($target);
                if ($group) {
                    $this->executeAction($group, $action, $value);
                    $output->writeln("<info>Applied {$action} to group '{$group->getName()}'</info>");
                } else {
                    $output->writeln("<error>Light or group '{$target}' not found</error>");
                    return Command::FAILURE;
                }
            }
        } else {
            $output->writeln('<error>Please specify a target, use --all, or --room</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function executeAction($target, string $action, $value = null): void
    {
        switch ($action) {
            case 'on':
                $target->on();
                break;
            case 'off':
                $target->off();
                break;
            case 'toggle':
                $target->toggle();
                break;
            case 'brightness':
                $target->setBrightness((int)$value);
                break;
            case 'color':
                $target->setColor($value);
                break;
        }
    }

    private function handleScene(InputInterface $input, OutputInterface $output): int
    {
        $client = $this->getClient();
        if (!$client) {
            $output->writeln('<error>Not configured. Run "hue setup" first.</error>');
            return Command::FAILURE;
        }

        $subAction = $input->getArgument('target');

        if ($subAction === 'activate') {
            $helper = $this->getHelper('question');
            $question = new Question('Enter scene name: ');
            $sceneName = $helper->ask($input, $output, $question);

            if ($sceneName) {
                $client->scenes()->activate($sceneName);
                $output->writeln("<info>Activated scene '{$sceneName}'</info>");
            }
        }

        return Command::SUCCESS;
    }

    private function runEffect(InputInterface $input, OutputInterface $output): int
    {
        $client = $this->getClient();
        if (!$client) {
            $output->writeln('<error>Not configured. Run "hue setup" first.</error>');
            return Command::FAILURE;
        }

        $effectType = $input->getArgument('target');
        $duration = (int)$input->getOption('duration');
        $room = $input->getOption('room');

        $target = $room ? $client->groups()->getByName($room) : $client->groups()->all();

        switch ($effectType) {
            case 'party':
                $effect = new ColorLoop($client);
                $effect->start($target, $duration);
                $output->writeln("<info>Started party effect for {$duration} seconds</info>");
                break;

            case 'sunrise':
                $target->sunrise($duration);
                $output->writeln("<info>Started sunrise effect for {$duration} seconds</info>");
                break;

            case 'sunset':
                $target->sunset($duration);
                $output->writeln("<info>Started sunset effect for {$duration} seconds</info>");
                break;

            case 'breathing':
                $effect = new Breathing($client);
                $effect->start($target, '#FFFFFF', 'medium', $duration);
                $output->writeln("<info>Started breathing effect for {$duration} seconds</info>");
                break;

            default:
                $output->writeln("<error>Unknown effect: {$effectType}</error>");
                return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function interactiveMode(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Entering interactive mode. Type "exit" to quit.</info>');
        $helper = $this->getHelper('question');

        while (true) {
            $question = new Question('hue> ');
            $command = $helper->ask($input, $output, $question);

            if (!$command) {
                continue;
            }

            if (trim($command) === 'exit') {
                break;
            }

            // Parse and execute command
            $parts = explode(' ', trim($command));
            // This would need more sophisticated parsing for a full interactive mode
            $output->writeln("Command: " . implode(' ', $parts));
        }

        $output->writeln('Goodbye!');
        return Command::SUCCESS;
    }

    private function getClient(): ?HueClient
    {
        if ($this->client) {
            return $this->client;
        }

        if (!isset($this->config['bridge_ip']) || !isset($this->config['username'])) {
            return null;
        }

        $this->client = new HueClient($this->config['bridge_ip'], $this->config['username']);
        return $this->client;
    }

    private function loadConfig(): void
    {
        if (file_exists($this->configFile)) {
            $content = file_get_contents($this->configFile);
            $this->config = json_decode($content, true) ?? [];
        }
    }

    private function saveConfig(): void
    {
        file_put_contents($this->configFile, json_encode($this->config, JSON_PRETTY_PRINT));
    }

    private function startServer(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Starting Hue REST API Server...</info>');

        $bridgeIp = $this->config['bridge_ip'] ?? null;
        $username = $this->config['username'] ?? null;
        $port = 8080;

        if (!$bridgeIp || !$username) {
            $output->writeln('<error>Bridge not configured. Run "hue setup" first.</error>');
            return Command::FAILURE;
        }

        $output->writeln("Bridge: {$bridgeIp}");
        $output->writeln("Port: {$port}");
        $output->writeln("Documentation: http://localhost:{$port}/docs");
        $output->writeln("API Base: http://localhost:{$port}/api");
        $output->writeln("");
        $output->writeln("Press Ctrl+C to stop the server");

        // Set environment variables for the server
        putenv("HUE_BRIDGE_IP={$bridgeIp}");
        putenv("HUE_USERNAME={$username}");

        // Start the built-in PHP server
        $publicPath = __DIR__ . '/../../public';
        $command = "php -S localhost:{$port} -t {$publicPath} {$publicPath}/api.php";

        passthru($command);
        return Command::SUCCESS;
    }

    private function listSensors(InputInterface $input, OutputInterface $output): int
    {
        $client = $this->getClient();
        if (!$client) {
            $output->writeln('<error>Not configured. Run "hue setup" first.</error>');
            return Command::FAILURE;
        }

        $sensors = $client->sensors()->getAll();

        $table = new Table($output);
        $table->setHeaders(['ID', 'Name', 'Type', 'Manufacturer', 'Battery']);

        foreach ($sensors as $sensor) {
            $battery = 'N/A';
            $config = $sensor->getConfig();
            if (isset($config['battery'])) {
                $battery = $config['battery'] . '%';
            }

            $table->addRow([
                $sensor->getId(),
                $sensor->getName(),
                $sensor->getType(),
                $sensor->getManufacturerName(),
                $battery
            ]);
        }

        $table->render();
        return Command::SUCCESS;
    }

    private function listSchedules(InputInterface $input, OutputInterface $output): int
    {
        $client = $this->getClient();
        if (!$client) {
            $output->writeln('<error>Not configured. Run "hue setup" first.</error>');
            return Command::FAILURE;
        }

        $schedules = $client->schedules()->getAll();

        $table = new Table($output);
        $table->setHeaders(['ID', 'Name', 'Time', 'Status', 'Auto Delete']);

        foreach ($schedules as $schedule) {
            $table->addRow([
                $schedule->getId(),
                $schedule->getName(),
                $schedule->getLocalTime(),
                $schedule->getStatus(),
                $schedule->isAutoDelete() ? 'Yes' : 'No'
            ]);
        }

        $table->render();
        return Command::SUCCESS;
    }

    private function showStatus(InputInterface $input, OutputInterface $output): int
    {
        $client = $this->getClient();
        if (!$client) {
            $output->writeln('<error>Not configured. Run "hue setup" first.</error>');
            return Command::FAILURE;
        }

        try {
            $config = $client->getConfig();
            $isConnected = $client->isConnected();

            $output->writeln('<info>Hue Bridge Status</info>');
            $output->writeln("Bridge IP: {$this->config['bridge_ip']}");
            $bridgeName = $config['name'] ?? 'Unknown';
            $output->writeln("Bridge Name: {$bridgeName}");
            $output->writeln("API Version: " . ($config['apiversion'] ?? 'Unknown'));
            $output->writeln("Software Version: " . ($config['swversion'] ?? 'Unknown'));
            $output->writeln("Model: " . ($config['modelid'] ?? 'Unknown'));
            $connectionStatus = $isConnected ? '<info>Connected</info>' : '<error>Disconnected</error>';
            $output->writeln("Connection: {$connectionStatus}");

            if ($isConnected) {
                $lightCount = count($client->lights()->getAll());
                $groupCount = count($client->groups()->getAll());
                $sceneCount = count($client->scenes()->getAll());

                $output->writeln("");
                $output->writeln("Resources:");
                $output->writeln("  Lights: {$lightCount}");
                $output->writeln("  Groups: {$groupCount}");
                $output->writeln("  Scenes: {$sceneCount}");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>Error getting status: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }

    private function showHelp(OutputInterface $output): void
    {
        $output->writeln("");
        $output->writeln("<info>Available commands:</info>");
        $output->writeln("  discover                 - Find Hue bridges on network");
        $output->writeln("  setup                    - Configure bridge connection");
        $output->writeln("  lights                   - List all lights");
        $output->writeln("  groups                   - List all groups/rooms");
        $output->writeln("  scenes                   - List all scenes");
        $output->writeln("  sensors                  - List all sensors");
        $output->writeln("  schedules                - List all schedules");
        $output->writeln("  status                   - Show bridge status");
        $output->writeln("  on [target]              - Turn on lights");
        $output->writeln("  off [target]             - Turn off lights");
        $output->writeln("  toggle [target]          - Toggle lights");
        $output->writeln("  brightness [value]       - Set brightness (0-100)");
        $output->writeln("  color [hex]              - Set color");
        $output->writeln("  scene activate           - Activate scene");
        $output->writeln("  effect [type]            - Run effect (party, sunrise, sunset, breathing)");
        $output->writeln("  server                   - Start REST API server");
        $output->writeln("  interactive              - Enter interactive mode");
        $output->writeln("");
        $output->writeln("<info>Options:</info>");
        $output->writeln("  --all                    - Apply to all lights");
        $output->writeln("  --room [name]            - Target specific room");
        $output->writeln("  --brightness [0-100]     - Set brightness");
        $output->writeln("  --color [hex]            - Set color");
        $output->writeln("  --duration [seconds]     - Effect duration");
    }
}
