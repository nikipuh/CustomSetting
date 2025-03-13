<?php
declare(strict_types=1);

namespace nikipuh;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ServerSettingsRequestPacket;
use pocketmine\network\mcpe\protocol\ServerSettingsResponsePacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;

class Main extends PluginBase implements Listener {
    private $players = [];
    
    private $lastFormSendTime = [];
    
    private $formJson;
    
    private $baseFormId = 2024;

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        
        $this->saveDefaultConfig();
        
        if (!is_dir($this->getDataFolder())) {
            mkdir($this->getDataFolder());
        }
        $this->saveResource("form.json", false);
        
        $formPath = $this->getDataFolder() . "form.json";
        if (!file_exists($formPath)) {
            $this->getLogger()->critical("Cannot find form.json! Creating default...");
            $defaultForm = [
                "type" => "custom_form",
                "title" => "§eHello world",
                "icon" => [
                    "type" => "path",
                    "data" => "textures/items/cookie"
                ],
                "content" => [
                    [
                        "type" => "label",
                        "text" => "Welcome to your custom setting page.\nYou can change, how this page looks in your form.json (plugin_data/CustomSetting/form.json)."
                    ],
                    [
                        "type" => "label",
                        "text" => "§l§cFORMATTING CODES REFERENCE:§r\n\n§0Black Text §1Dark Blue §2Dark Green §3Dark Aqua\n§4Dark Red §5Dark Purple §6Gold §7Gray\n§8Dark Gray §9Blue §aGreen §bAqua\n§cRed §dLight Purple §eYellow §fWhite\n\n§lBold Text§r §oItalic Text§r\n§kObfuscated Text§r §rReset Formatting\n\n§9Combine §l§9formatting §l§9§ocodes§r for §c§l§ncreative§r text!\n"
                    ],
                    [
                        "type" => "label",
                        "text" => "§l§cSERVER INFO§r\n\n§6Welcome to our Minecraft server!§r\n\n§bServer Rules:§r\n§a1. §fBe respectful to all players\n§a2. §fNo griefing or stealing\n§a3. §fNo hacking or using unfair advantages\n§a4. §fHave fun!\n\n§l§9SERVER FEATURES:§r\n§e• §dCustom enchantments\n§e• §dWeekly events\n§e• §dFriendly community\n§e• §dPlayer shops\n\n§o§7Contact us at example@server.com for support§r\n\n§l§2DONATION INFO:§r\n§fSupport our server by donating at §nwww.serverdonation.com§r\n\n§kMYSTERY TEXT§r §l§4IMPORTANT§r §o§5NOTICE§r\n\n§r"
                    ]
                ]
            ];
            $this->formJson = json_encode($defaultForm);
            file_put_contents($formPath, $this->formJson);
        } else {
            $this->formJson = file_get_contents($formPath);
            if (!$this->isValidJson($this->formJson)) {
                $this->getLogger()->critical("Invalid JSON in form.json! Using default...");
                $defaultForm = [
                    "type" => "custom_form",
                    "title" => "§eHello world",
                    "icon" => [
                        "type" => "path",
                        "data" => "textures/items/cookie"
                    ],
                    "content" => [
                        [
                            "type" => "label",
                            "text" => "Welcome to your custom setting page.\nYou can change, how this page looks in your form.json (plugin_data/CustomSetting/form.json)."
                        ],
                        [
                            "type" => "label",
                            "text" => "§l§cFORMATTING CODES REFERENCE:§r\n\n§0Black Text §1Dark Blue §2Dark Green §3Dark Aqua\n§4Dark Red §5Dark Purple §6Gold §7Gray\n§8Dark Gray §9Blue §aGreen §bAqua\n§cRed §dLight Purple §eYellow §fWhite\n\n§lBold Text§r §oItalic Text§r\n§kObfuscated Text§r §rReset Formatting\n\n§9Combine §l§9formatting §l§9§ocodes§r for §c§l§ncreative§r text!\n"
                        ],
                        [
                            "type" => "label",
                            "text" => "§l§cSERVER INFO§r\n\n§6Welcome to our Minecraft server!§r\n\n§bServer Rules:§r\n§a1. §fBe respectful to all players\n§a2. §fNo griefing or stealing\n§a3. §fNo hacking or using unfair advantages\n§a4. §fHave fun!\n\n§l§9SERVER FEATURES:§r\n§e• §dCustom enchantments\n§e• §dWeekly events\n§e• §dFriendly community\n§e• §dPlayer shops\n\n§o§7Contact us at example@server.com for support§r\n\n§l§2DONATION INFO:§r\n§fSupport our server by donating at §nwww.serverdonation.com§r\n\n§kMYSTERY TEXT§r §l§4IMPORTANT§r §o§5NOTICE§r\n\n§r"
                        ]
                    ]
                ];
                $this->formJson = json_encode($defaultForm);
            }
        }
        
        // Keep periodic refresh task for players who don't interact with the form
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function(): void {
                $this->getLogger()->debug("Running form refresh check");
                $currentTime = time();
                foreach ($this->getServer()->getOnlinePlayers() as $player) {
                    $name = $player->getName();
                    
                    if (!isset($this->lastFormSendTime[$name]) || ($currentTime - $this->lastFormSendTime[$name] >= 3)) {
                        $this->sendSettingsForm($player);
                    }
                }
            }
        ), 60); // 60 ticks = 3 seconds
    }
    
    private function isValidJson(string $json): bool {
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();
        
        $this->players[$name] = [
            "formId" => $this->baseFormId,
            "rotation" => 0
        ];
        
        // Send form after 3 seconds to wait for the player to fully join
        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(
            function() use ($player, $name): void {
                if ($player->isOnline()) {
                    $this->sendSettingsForm($player, true);
                    $this->getLogger()->debug("Initial settings form sent to $name");
                }
            }
        ), 3);
    }
    
    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $name = $event->getPlayer()->getName();
        
        unset($this->players[$name]);
        unset($this->lastFormSendTime[$name]);
        
        $this->getLogger()->debug("Cleaned up form data for $name");
    }
    
    public function onDataPacketReceive(DataPacketReceiveEvent $event): void {
        $packet = $event->getPacket();
        $player = $event->getOrigin()->getPlayer();
        
        if ($player === null) {
            return;
        }
        
        $name = $player->getName();
        
        if ($packet instanceof ServerSettingsRequestPacket) {
            $this->getLogger()->debug("$name requested settings form");
            // Send immediately with priority when user opens settings
            $this->sendSettingsForm($player, true);
            return;
        }
        
        if ($packet instanceof ModalFormResponsePacket) {
            if (!isset($this->players[$name])) {
                return;
            }
            
            $formId = $packet->formId;
            
            $validIds = [];
            for ($i = 0; $i < 10; $i++) {
                $validIds[] = $this->baseFormId + $i;
            }
            
            if (!in_array($formId, $validIds)) {
                return;
            }
            
            $this->getLogger()->debug("$name interacted with settings form (ID: $formId)");
            
            $formData = $packet->formData;
            if ($formData !== "null") {
                $this->getLogger()->debug("$name submitted data in settings form");
                $data = json_decode($formData, true);
            }
            
            $this->rotatePlayerFormId($name);
            
            // Send form immediately without scheduling
            if ($player->isOnline()) {
                $this->sendSettingsForm($player, true);
            }
        }
    }
    
    private function rotatePlayerFormId(string $playerName): void {
        if (!isset($this->players[$playerName])) {
            $this->players[$playerName] = [
                "formId" => $this->baseFormId,
                "rotation" => 0
            ];
            return;
        }
        
        $currentRotation = $this->players[$playerName]["rotation"];
        $newRotation = ($currentRotation + 1) % 10;
        
        $this->players[$playerName]["formId"] = $this->baseFormId + $newRotation;
        $this->players[$playerName]["rotation"] = $newRotation;
        
        $this->getLogger()->debug("Rotated form ID for $playerName to " . $this->players[$playerName]["formId"]);
    }
    
    // prevent spamming forms
    private function verifyFormReceived(Player $player): bool {
        if (!$player->isOnline()) {
            return false;
        }

        $name = $player->getName();
        if (!isset($this->lastFormSendTime[$name])) {
            return false;
        }
        
        $currentTime = time();
        return ($currentTime - $this->lastFormSendTime[$name] < 3);
    }
    
    public function sendSettingsForm(Player $player, bool $bypass_throttle = false): bool {
        if (!$player->isOnline()) {
            return false;
        }
        
        $name = $player->getName();
        
        // Check if player has received a form recently and bypass throttle if requested (when player opens settings)
        if (!$bypass_throttle && $this->verifyFormReceived($player)) {
            $this->getLogger()->debug("Player $name has already received a form recently.");
            return false;
        }

        try {
            if (!isset($this->players[$name])) {
                $this->players[$name] = [
                    "formId" => $this->baseFormId,
                    "rotation" => 0
                ];
            }
            
            $formId = $this->players[$name]["formId"];
            
            $pk = new ServerSettingsResponsePacket();
            $pk->formId = $formId;
            $pk->formData = $this->formJson;
            
            // Always send with highest priority when requested explicitly
            $player->getNetworkSession()->sendDataPacket($pk, $bypass_throttle);
            
            $this->lastFormSendTime[$name] = time();
            
            return true;
        } catch (\Throwable $e) {
            $this->getLogger()->error("Error sending settings form to $name: " . $e->getMessage());
            return false;
        }
    }
}