<?php

namespace bonanoo\Entity\pathfinder;

use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\world\World;

class DataWorld {
    private int $height;
    private int $halfWidth;

    public function __construct(
        private World  $world,
        private Entity $entity
    ) {
        $size = $this->entity->getSize();
        $this->halfWidth = (int)round($size->getWidth() / 2, PHP_ROUND_HALF_DOWN);
        $this->height = (int)ceil(max($size->getHeight() - 1, 1));
    }


    public function getBlockAt(int $x, int $y, int $z): Block {
        return $this->world->getBlockAt($x, $y, $z);
    }

    public function getBlock(Vector3 $vector3): Block {
        return $this->getBlockAt($vector3->getFloorX(), $vector3->getFloorY(), $vector3->getFloorZ());
    }


    public function hasBlockBelow(Vector3 $center): bool {
        $halfWidth = $this->halfWidth;
        for ($xOffset = -$halfWidth; $xOffset <= $halfWidth; $xOffset++) {
            for ($zOffset = -$halfWidth; $zOffset <= $halfWidth; $zOffset++) {
                $blockBelow = $center->add($xOffset, -1, $zOffset);

                if ($this->isSolid($this->getBlock($blockBelow))) {
                    return true;
                }
            }
        }
        return false;
    }
    public function couldStandAt(Vector3 $node): bool {
        // TODO: Implement couldStandAt() method.
        return $this->hasBlockBelow($node) && $this->isAreaEmpty($node);
    }

    public function isAreaEmpty(Vector3 $center): bool {
        $halfWidth = $this->halfWidth;
        $height = $this->height;

        for ($xOffset = -$halfWidth; $xOffset <= $halfWidth; $xOffset++) {
            for ($zOffset = -$halfWidth; $zOffset <= $halfWidth; $zOffset++) {
                for ($yOffset = 0; $yOffset <= $height; $yOffset++) {
                    $block = $this->getBlock($center->add($xOffset, $yOffset, $zOffset));

                    if (!$this->isPassable($block)) {
                        return false;
                    }
                }
            }
        }
        return true;
    }


    public function isPassable(Block $block): bool {
        return $block->canBeReplaced();
    }

    public function isSolid(Block $block): bool {
        // TODO: Implement isSolid() method.
        return $block->isFullCube();
    }


}
