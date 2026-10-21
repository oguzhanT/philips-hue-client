<?php

namespace OguzhanTogay\HueClient\Console;

use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Discovery\BridgeDiscovery;
use OguzhanTogay\HueClient\Effects\ColorLoop;
use OguzhanTogay\HueClient\Effects\Breathing;
use OguzhanTogay\HueClient\Effects\Alert;
use OguzhanTogay\HueClient\Security\BridgeCertificate;
use OguzhanTogay\HueClient\Signal\Signaler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
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
            ->addOption('room', 'r', InputOption::VALUE_REQUIRED, 'Target room')
            ->addOption(
                'light',
                'l',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Signal: light name or id (repeatable)'
            )
            ->addOption('restore', null, InputOption::VALUE_REQUIRED, 'Signal: restore previous state after N seconds');
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
                case 'server':
                    return $this->startServer($input, $output);
                case 'sensors':
                    return $this->listSensors($input, $output);
                case 'schedules':
                    return $this->listSchedules($input, $output);
                case 'status':
                    return $this->showStatus($input, $output);
                case 'signal':
                    return $this->sendSignal($input, $output);
                case 'pause':
                    return $this->pauseSignals($input, $output);
                case 'pin':
                    return $this->pinCertificate($input, $output);
                case 'events':
                    return $this->watchEvents($input, $output);
                case 'resume':
                    (new Signaler(null))->resume();
                    $output->writeln('<info>Signals resumed.</info>');
                    return Command::SUCCESS;
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
        /** @var QuestionHelper $helper */
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

        // Pin the bridge's certificate before the token ever crosses the network.
        try {
            $certificate = BridgeCertificate::fetch($bridge->getIp());
        } catch (\Exception $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        if (!$certificate->matchesBridgeId($bridge->getId())) {
            $output->writeln(
                "<error>The certificate at {$bridge->getIp()} is for '{$certificate->commonName}', "
                . "not bridge {$bridge->getId()}. Not pairing.</error>"
            );
            return Command::FAILURE;
        }

        $client = new HueClient($bridge->getIp(), null, ['pinned_public_key' => $certificate->pin]);

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
                'username' => $username,
                'pinned_public_key' => $certificate->pin,
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
            /** @var QuestionHelper $helper */
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


    private function getClient(): ?HueClient
    {
        if ($this->client) {
            return $this->client;
        }

        if (!isset($this->config['bridge_ip']) || !isset($this->config['username'])) {
            return null;
        }

        $this->client = new HueClient(
            $this->config['bridge_ip'],
            $this->config['username'],
            ['pinned_public_key' => $this->config['pinned_public_key'] ?? null]
        );
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
        // The file holds the bridge token: create it readable by the owner only.
        $old = umask(0077);
        file_put_contents($this->configFile, json_encode($this->config, JSON_PRETTY_PRINT));
        umask($old);
        @chmod($this->configFile, 0600);
    }

    private function startServer(InputInterface $input, OutputInterface $output): int
    {
        $bridgeIp = $this->config['bridge_ip'] ?? null;
        $username = $this->config['username'] ?? null;

        if (!$bridgeIp || !$username) {
            $output->writeln('<error>Bridge not configured. Run "hue setup" first.</error>');
            return Command::FAILURE;
        }

        // bin/hue-server does the checks (API key, bridge reachable) and starts
        // PHP's built-in server; HUE_API_KEY, HUE_PORT, HUE_BIND and friends
        // are read from the environment. The username goes through the
        // environment too: arguments are visible to every user in `ps`.
        $env = array_merge(getenv(), ['HUE_BRIDGE_IP' => $bridgeIp, 'HUE_USERNAME' => $username]);
        $command = [PHP_BINARY, __DIR__ . '/../../bin/hue-server'];
        $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, null, $env);

        return $process !== false && proc_close($process) === 0 ? Command::SUCCESS : Command::FAILURE;
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

    private function sendSignal(InputInterface $input, OutputInterface $output): int
    {
        $type = $input->getArgument('target');
        if (!$type) {
            $output->writeln(
                '<error>Usage: hue signal <attention|success|error|warning|working|off> [--light=Desk]</error>'
            );
            return Command::FAILURE;
        }

        $client = $this->getClient();
        if (!$client) {
            $output->writeln('<error>Not configured. Run "hue setup" first.</error>');
            return Command::FAILURE;
        }

        $lights = $input->getOption('light') ?: null;
        $restore = $input->getOption('restore');

        if (!$client->signal()->send($type, $lights, $restore !== null ? (int) $restore : null)) {
            $output->writeln('<comment>Signals are paused; nothing changed. Run "hue resume".</comment>');
            return Command::SUCCESS;
        }

        $output->writeln("<info>Signal '{$type}' sent.</info>");
        return Command::SUCCESS;
    }

    /**
     * Pins the certificate of the configured bridge (for setups made before 2.1).
     */
    private function pinCertificate(InputInterface $input, OutputInterface $output): int
    {
        $ip = $this->config['bridge_ip'] ?? null;
        if (!$ip) {
            $output->writeln('<error>Not configured. Run "hue setup" first.</error>');
            return Command::FAILURE;
        }

        $certificate = BridgeCertificate::fetch($ip);
        $output->writeln("Certificate for: {$certificate->commonName} (issued by {$certificate->issuer})");
        $output->writeln("Public key pin:  {$certificate->pin}");

        if (!empty($this->config['pinned_public_key']) && $this->config['pinned_public_key'] !== $certificate->pin) {
            $output->writeln('<error>This is NOT the key pinned before. Not changing anything.</error>');
            $output->writeln(
                'If you replaced the bridge, remove pinned_public_key from ' . $this->configFile . ' first.'
            );
            return Command::FAILURE;
        }

        $this->config['pinned_public_key'] = $certificate->pin;
        $this->saveConfig();
        $output->writeln('<info>Pinned. Requests to any other key will be refused.</info>');

        return Command::SUCCESS;
    }

    private function watchEvents(InputInterface $input, OutputInterface $output): int
    {
        $client = $this->getClient();
        if (!$client) {
            $output->writeln('<error>Not configured. Run "hue setup" first.</error>');
            return Command::FAILURE;
        }

        // --duration defaults to 30 for effects; here only an explicit value limits listening.
        $given = $input->hasParameterOption(['--duration', '-d']);
        $seconds = $given ? (float) $input->getOption('duration') : null;
        $output->writeln('<info>Listening to the bridge (CLIP v2 event stream). Ctrl+C to stop.</info>');

        $client->v2()->events()->listen(function (array $event) use ($output): bool {
            foreach ($event['data'] ?? [] as $change) {
                $fields = array_diff_key($change, array_flip(['id', 'id_v1', 'type', 'owner']));
                $output->writeln(sprintf(
                    '%s  %-6s %-14s %s  %s',
                    date('H:i:s'),
                    $event['type'] ?? '?',
                    $change['type'] ?? '?',
                    $change['id_v1'] ?? $change['id'] ?? '',
                    json_encode($fields)
                ));
            }
            return true;
        }, $seconds);

        return Command::SUCCESS;
    }

    private function pauseSignals(InputInterface $input, OutputInterface $output): int
    {
        $minutes = $input->getArgument('target');
        if ($minutes !== null && (!ctype_digit((string) $minutes) || (int) $minutes === 0)) {
            $output->writeln('<error>Usage: hue pause [minutes], minutes a whole number above 0</error>');
            return Command::FAILURE;
        }

        $signaler = new Signaler(null);
        $signaler->pause($minutes !== null ? (int) $minutes * 60 : null);

        $output->writeln($minutes !== null
            ? "<info>Signals paused for {$minutes} minutes.</info>"
            : '<info>Signals paused until "hue resume".</info>');
        return Command::SUCCESS;
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
        $output->writeln("  signal [type]            - attention, success, error, warning, working, off");
        $output->writeln("  events                   - Watch changes live (--duration to stop after N seconds)");
        $output->writeln("  pin                      - Pin the bridge's certificate (done by setup since 2.1)");
        $output->writeln("  pause [minutes]          - Pause signals (also pauses claude-hue-notify)");
        $output->writeln("  resume                   - Resume signals");
        $output->writeln("  server                   - Start REST API server");
        $output->writeln("");
        $output->writeln("<info>Options:</info>");
        $output->writeln("  --all                    - Apply to all lights");
        $output->writeln("  --room [name]            - Target specific room");
        $output->writeln("  --brightness [0-100]     - Set brightness");
        $output->writeln("  --color [hex]            - Set color");
        $output->writeln("  --duration [seconds]     - Effect duration");
        $output->writeln("  --light [name|id]        - Signal target, repeatable (default: notifier lamps)");
        $output->writeln("  --restore [seconds]      - Signal: restore previous state afterwards");
    }
}
