<?php

declare(strict_types=1);

namespace tgwaste\Weather;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener {
	public static $instance;

	public $timer;
	public $weather;

	public const CLEAR = 0;
	public const LIGHT_RAIN = 1;
	public const MODERATE_RAIN = 2;
	public const HEAVY_RAIN = 3;
	public const LIGHT_THUNDER = 4;
	public const MODERATE_THUNDER = 5;
	public const HEAVY_THUNDER = 6;

	protected function onEnable() : void {
		self::$instance = $this;

		$this->saveDefaultConfig();

		if ($this->getConfig()->get("startclear") == true) {
			(new Weather)->switchWeather(Main::CLEAR);
		} else {
			(new Weather)->switchWeather(mt_rand(Main::CLEAR, Main::HEAVY_THUNDER));
		}

		$this->getScheduler()->scheduleRepeatingTask(new Schedule(), 20);
		$this->getServer()->getPluginManager()->registerEvents(new Listen(), $this);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool {
		if ($sender instanceof Player and !$sender->hasPermission(DefaultPermissions::ROOT_OPERATOR)) {
			$sender->sendMessage("§cYou do not have permission to make weather changes§r");
			return true;
		}

		if (count($args) < 1) {
			return false;
		}

		if ($args[0] === "clear") {
			(new Weather)->switchWeather(Main::CLEAR);
			return true;
		}

		if ($args[0] === "rain") {
			(new Weather)->switchWeather(Main::MODERATE_RAIN);
			return true;
		}

		if ($args[0] === "thunder") {
			(new Weather)->switchWeather(Main::MODERATE_THUNDER);
			return true;
		}

		if ($args[0] === "status") {
			$sender->sendMessage((new Weather)->weatherStatus());
			return true;
		}

		return false;
	}
}
