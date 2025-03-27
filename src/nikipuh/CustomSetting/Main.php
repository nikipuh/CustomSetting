<?php
declare(strict_types=1);

namespace nikipuh\CustomSetting;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ServerSettingsRequestPacket;
use pocketmine\network\mcpe\protocol\ServerSettingsResponsePacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;

class Main extends PluginBase implements Listener {
     /** @var array */
     private $intervals = [1, 2, 3, 5, 10];
     //incase the player bought his device from temu
     //and it takes 10 seconds to open the settings
    
     /** @var array<string, int> */
     private array $playerIndices = [];
     
     /** @var array<string, \pocketmine\scheduler\TaskHandler|null> */
     private array $taskHandlers = [];
    
    /** @var string */
    private string $formJson;
    
    /** @var int Form ID constant */
    private const SETTINGS_FORM_ID = 6969;
    // Just a random number ;)

    /** @var array<string, bool> */
    private array $players = [];

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        
        if (!is_dir($this->getDataFolder())) {
            mkdir($this->getDataFolder());
        }

        $this->loadFormJson();
    }
    
    /**
     * Load the form JSON from file or create default
     */
    private function loadFormJson(): void {
        $this->saveResource("form.json", false);
        $formPath = $this->getDataFolder() . "form.json";
        
        if (file_exists($formPath)) {
            $content = file_get_contents($formPath);
            if ($content !== false && $this->isValidJson($content)) {
                $this->formJson = $content;
                return;
            }
            $this->getLogger()->critical("Invalid JSON in form.json! Using default...");
        } else {
            $this->getLogger()->critical("Cannot find form.json! Creating default...");
        }
        
        // Create default form
        $defaultForm = $this->getDefaultForm();
        $this->formJson = json_encode($defaultForm);
        file_put_contents($formPath, $this->formJson);
    }
    
    /**
     * Get the default form data
     * @return array
     */
    private function getDefaultForm(): array {
        //Just incase the form.json is missing or invalid
        //also made it smaller since i have all the formatting in the readme file :)
        return [
            "type" => "custom_form",
            "title" => "§eServer Settings",
            "icon" => [
                "type" => "path",
                "data" => "textures/items/cookie"
            ],
            "content" => [
                [
                    "type" => "label",
                    "text" => "§lWelcome to your custom setting page.§r\n§7You can change how this page looks in form.json.\nCheck the README for more info."
                ]
            ]
        ];
    }
    
    /**
     * Validate if a string is valid JSON
     * @param string $json
     * @return bool
     */
    private function isValidJson(string $json): bool {
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Stop all running tasks for a player
     * @param Player $player
     */
    private function stopTasks(Player $player): void {
        $name = $player->getName();
        
        if (isset($this->taskHandlers[$name]) && $this->taskHandlers[$name] !== null) {
            $this->taskHandlers[$name]->cancel();
            $this->taskHandlers[$name] = null;
            $this->getLogger()->debug("Task for {$name} stopped");
        }
        
        // Reset index
        $this->playerIndices[$name] = 0;
    }

    /**
     * Schedule the next task to send the form again (times the amount of intervals)
     * @param Player $player The player to send the form to
     */
    public function scheduleNextTask(Player $player): void {
        $name = $player->getName();
        
        if (!isset($this->playerIndices[$name])) {
            $this->playerIndices[$name] = 0;
        }
        
        if ($this->playerIndices[$name] >= count($this->intervals)) {
            $this->getLogger()->debug("All intervals done, stopping the task");
            return; // stop the sequence after all intervals are done
        }
        
        $interval = $this->intervals[$this->playerIndices[$name]];
        $this->playerIndices[$name]++;
        
        $this->getLogger()->info("Planning task #{$interval} for " . $player->getName());
        
        if (isset($this->taskHandlers[$name]) && $this->taskHandlers[$name] !== null) {
            $this->taskHandlers[$name]->cancel();
        }
        
        $this->taskHandlers[$name] = $this->getScheduler()->scheduleDelayedTask(
            new class($this, $player) extends \pocketmine\scheduler\Task {
                /** @var Main */
                private $plugin;
                /** @var Player */
                private $player;
                
                public function __construct(Main $plugin, Player $player) {
                    $this->plugin = $plugin;
                    $this->player = $player;
                }
                
                public function onRun(): void {
                    if ($this->player->isOnline()) {
                        $this->plugin->sendSettingsForm($this->player);
                        $this->plugin->scheduleNextTask($this->player);
                    }else{
                        $this->plugin->getLogger()->debug("Player " . $this->player->getName() . " is offline, cannot send form");
                    }
                }
            }, 
            $interval * 20
        );
    }
  
    /**
     * Handle packets received from client
     */
    public function onDataPacketReceive(DataPacketReceiveEvent $event): void {
        $packet = $event->getPacket();
        $player = $event->getOrigin()->getPlayer();
        
        if ($player === null) {
            return;
        }
        
        $name = $player->getName();
        
        // Handle settings request packet (client opened settings)
        if ($packet instanceof ServerSettingsRequestPacket) {
            // Stop any running tasks
            $this->stopTasks($player);
            // Start the sequence of form sends
            $this->scheduleNextTask($player);
            $event->cancel(); // Mark as handled
            $this->getLogger()->debug("Caught ServerSettingsRequestPacket (" . $player->getName() . " opened the settings)");
            return;
        }
        
        // Handle form response packet (client submitted form/ opened the CustomSettings page)
        if ($packet instanceof ModalFormResponsePacket && $packet->formId === self::SETTINGS_FORM_ID) {
            $this->handleFormResponse($player, $packet->formData);
            $this->getLogger()->debug("Caught response on Settings form by " . $player->getName() . " and sent it to handleFormResponse (Player opened the CustomSettings page)");
            $event->cancel(); // Mark as handled
            
            if ($player->isOnline()) {
                $this->getLogger()->debug("Caught ServerSettingsRequestPacket interaction (" . $player->getName() . " closed the settings)");
                $this->stopTasks($player); // Stop tasks when settings menu is closed
            }
        }
    }
    
    /**
     * Process form response data
     * @param Player $player
     * @param string $formData
     */
    private function handleFormResponse(Player $player, string $formData): void {
        $name = $player->getName();
        
        if ($formData === "null") {
            $this->getLogger()->debug("Form response was null for " . $name);
            $this->stopTasks($player); // Stop tasks when form is closed
            return; // Form was closed without submission
        }
        
        try {
            // Try to decode the data as JSON first
            $data = json_decode($formData, true);
            
            // If not valid JSON, try base64 decoding
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                $decodedData = base64_decode($formData, true);
                if ($decodedData !== false) {
                    $data = json_decode($decodedData, true);
                }
            }
            
            // Process form data here if needed
            // Currently the form only has labels, but you can add interactive elements
            // and process them here
            
        } catch (\Throwable $e) {
            $this->getLogger()->error("Error processing form response: " . $e->getMessage());
        }
    }
    
    /**
     * Send settings form to player
     * @param Player $player
     * @return bool Success
     */
    public function sendSettingsForm(Player $player): bool {
        if (!$player->isOnline()) {
            $this->getLogger()->debug("Player " . $player->getName() . " is offline, cannot send form");
            return false;
        }
        
        $name = $player->getName();

        try {
            // Track player
            $this->players[$name] = true;
            
            // Create and send packet
            $pk = new ServerSettingsResponsePacket();
            $pk->formId = self::SETTINGS_FORM_ID;
            $pk->formData = $this->formJson;
            
            $player->getNetworkSession()->sendDataPacket($pk);
            $this->getLogger()->debug("Sent CustomSetting to " . $name);
            
            return true;
        } catch (\Throwable $e) {
            $this->getLogger()->error("Error sending CustomSetting form: " . $e->getMessage());
            return false;
        }
    }
}