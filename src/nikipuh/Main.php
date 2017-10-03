<?php

namespace nikipuh;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\network\mcpe\protocol\ServerSettingsRequestPacket;
use pocketmine\network\mcpe\protocol\ServerSettingsResponsePacket;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

class Main extends PluginBase implements Listener {

	public function onEnable() {
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		if(!is_dir($this->getDataFolder())) {
			mkdir($this->getDataFolder());
		}
		$this->saveResource("Setting.json");
        }
	/**
	 * @param DataPacketReceiveEvent $event
	 */
	public function onDataPacket(DataPacketReceiveEvent $event) {
		$packet = $event->getPacket();
		if($packet instanceof ServerSettingsRequestPacket) {
			$packet = new ServerSettingsResponsePacket();
			$packet->formData = file_get_contents($this->getDataFolder() . "Setting.json");
			$packet->formId = 5928;
			$event->getPlayer()->dataPacket($packet);
		} elseif($packet instanceof ModalFormResponsePacket) {
			$formId = $packet->formId;
			if($formId !== 5928) {
				return;
			}
		}
	}
}
?>