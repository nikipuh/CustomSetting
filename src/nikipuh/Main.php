<?php
declare(strict_types=1);

namespace nikipuh;

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
    
     /** @var int */
     private $currentIndex = 0;
     
     /** @var \pocketmine\scheduler\TaskHandler|null */
     private $taskHandler = null;
    
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
     * Schedule the next task to send the form again (times the amount of intervals)
     * @param Player $player The player to send the form to
     */
    public function scheduleNextTask(Player $player): void {
        if ($this->currentIndex >= count($this->intervals)) {
            $this->getLogger()->debug("All intervals done, stopping the task");
            return; // stop the sequence after all intervals are done
        }
        
        $interval = $this->intervals[$this->currentIndex];
        $this->currentIndex++;
        
        $this->getLogger()->info("Planning task #{$interval} for " . $player->getName());
        
        $this->taskHandler = $this->getScheduler()->scheduleDelayedTask(
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
                        $this->plugin->getLogger()->debug("Preparing form for " . $this->player->getName());
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
        
        // Handle settings request packet (client opened settings)
        if ($packet instanceof ServerSettingsRequestPacket) {
            // Reset index for new sequence
            $this->currentIndex = 0;
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