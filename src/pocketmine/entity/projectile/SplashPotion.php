<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\entity\projectile;

use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\item\Potion;
use pocketmine\level\Level;
use pocketmine\level\particle\SplashPotionParticle;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\utils\Color;

class SplashPotion extends Throwable{
	public const NETWORK_ID = self::SPLASH_POTION;

	public const DATA_POTION_ID = 37;

	public $width = 0.25;
	public $height = 0.25;

	protected $gravity = 0.1;
	protected $drag = 0.05;

	protected $hasSplashed = false;

	public function __construct(Level $level, CompoundTag $nbt, Entity $shootingEntity = null){
		if(!isset($nbt->PotionId)){
			$nbt->PotionId = new ShortTag("PotionId", Potion::AWKWARD);
		}
		parent::__construct($level, $nbt, $shootingEntity);
		$this->setDataProperty(self::DATA_POTION_ID, self::DATA_TYPE_SHORT, $this->getPotionId());
	}

	public function getPotionId() : int{
		return (int) $this->namedtag["PotionId"];
	}

	protected function splash(){
		if(!$this->hasSplashed){
			$this->hasSplashed = true;
			$effects = Potion::getPotionEffectsById($this->getPotionId());
			$colors = [new Color(0x38, 0x5d, 0xc6)];
			if(count($effects) > 0){
				$colors = [];
				foreach($effects as $effect){
					for($i = 0; $i < $effect->getEffectLevel(); ++$i){
						$colors[] = $effect->getColor();
					}
				}
			}
			$color = Color::mix(...$colors);
			$this->getLevel()->addParticle(new SplashPotionParticle($this, $color->getR(), $color->getG(), $color->getB()));
			$this->getLevel()->broadcastLevelSoundEvent($this, LevelSoundEventPacket::SOUND_GLASS);
			foreach($this->getLevel()->getNearbyEntities($this->getBoundingBox()->grow(4.125, 2.125, 4.125)) as $e){
				if($e instanceof Living){
					$distanceSquared = $e->distanceSquared($this);
					if($distanceSquared > 16){
						continue;
					}
					$modifier = 0.25 * (4 - floor(sqrt($distanceSquared)));
					foreach($effects as $effect){
						if($effect->isInstant()){
							if($modifier <= 0){
								continue;
							}
							$effect->setMultiplier($modifier);
						}else{
							$duration = (int) round($effect->getDuration() * $modifier * 0.75);
							if($duration < 20){
								continue;
							}
							$effect->setDuration($duration);
						}
						$e->addEffect($effect);
					}
				}
			}
		}
	}

	public function onCollideWithEntity(Entity $entity){
		$this->flagForDespawn();
	}

	public function flagForDespawn() : void{
		$this->splash();
		parent::flagForDespawn();
	}
}
