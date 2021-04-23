<?php

declare(strict_types=1);

namespace tgwaste\Weather;

use pocketmine\scheduler\Task;

class Schedule extends Task {
	public function onRun() : void {
		Main::$instance->timer -= 1;

		if (Main::$instance->weather >= Main::LIGHT_THUNDER) {
			if (mt_rand(1, 100) >= 95) {
				(new Weather)->sendLightning();
				(new Weather)->playThunder();
			}
		}

		if (!Main::$instance->timer) {
			(new Weather)->switchWeather(-1);
		}
	}
}

