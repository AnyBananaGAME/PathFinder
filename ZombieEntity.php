<?php
namespace bonanoo\Entity;

use bonanoo\Entity\pathfinder\DataWorld;
use bonanoo\Entity\pathfinder\Node;
use pocketmine\entity\Zombie;
use pocketmine\math\Vector3;
use pocketmine\entity\Location;
use pocketmine\utils\TextFormat;
use pocketmine\world\particle\HeartParticle;
use SplPriorityQueue;

class ZombieEntity extends Zombie {
    private DataWorld $dataWorld;
    private Vector3 $goal;
    private bool $isPathfinding = false;
    private ?array $path = null;

    public function __construct(Location $location) {
        parent::__construct($location);
        $this->dataWorld = new DataWorld($this->getWorld(), $this);
        $this->setCanSaveWithChunk(false);
        $this->setNameTagAlwaysVisible();
        $this->setNameTag("NameToBeChanged");
        $this->goal = $this->getPosition()->add(1, 0, 1);
    }

    public function setGoal(Vector3 $goal): void {
        $this->goal = $goal;
        $this->isPathfinding = true;
        $this->path = $this->findPath(Node::fromVector3($this->getPosition()->asVector3()), Node::fromVector3($this->goal));
        if ($this->path) {
            error_log("Path calculated: " . implode(" -> ", array_map(fn($v) => $v->__toString(), $this->path)));
        } else {
            error_log("No path found to goal: " . $goal->__toString());
        }
    }

    public function onUpdate(int $currentTick): bool {
        if ($this->isPathfinding && $currentTick % 4 === 0) {
            if ($this->path && count($this->path) > 0) {
                $nextStep = array_shift($this->path);
                error_log("Moving to: " . $nextStep->__toString());
                $this->lookAt($nextStep);
                $this->teleport($nextStep);
                $this->getWorld()->addParticle($this->goal, new HeartParticle(2));
                $a = TextFormat::RED;
                $b = TextFormat::AQUA;
                $this->setNameTag("Monkey?\n$a Goals: $b".count($this->path)."\n$a Pos:$b $nextStep");
                if (count($this->path) === 0) {
                    error_log("Goal reached: " . $this->goal->__toString());
                    $this->isPathfinding = false;
                }
            } else {
                $this->isPathfinding = false;
            }
        }
        return parent::onUpdate($currentTick);
    }
    // TODO: Maybe Make this somehow less resource hungry?
    private function findPath(Node $start, Node $goal): ?array {
        if ($start->distance($goal) < 1) {
            return [$goal];
        }
        $openSet = new SplPriorityQueue();
        $start->setG(0);
        $start->setH($this->heuristic($start, $goal));
        $openSet->insert($start, -$start->getF());
        $closedSet = []; // Fix that later if i find out why it was crashing
        $maxNodes = 10000;
        $iterations = 0;

        while (!$openSet->isEmpty() && $maxNodes-- > 0) {
            /** @var Node $current */
            $current = $openSet->extract();

            if ($current->equals($goal)) {
                error_log("Path found after $iterations iterations");
                return $this->reconstructPath($current);
            }

            $closedSet[$current->getHash()] = true;
            $iterations++;

            foreach ($this->getNeighbours($current) as $neighbor) {
                if (isset($closedSet[$neighbor->getHash()])) {
                    continue;
                }

                $tentativeG = $current->getG() + $current->distance($neighbor);
                if ($tentativeG < $neighbor->getG()) {
                    $neighbor->setParentNode($current);
                    $neighbor->setG($tentativeG);
                    $neighbor->setH($this->heuristic($neighbor, $goal));
                    $openSet->insert($neighbor, -$neighbor->getF());
                }
            }
        }

        error_log("No path fond after $iterations iterations");
        return null;
    }

    private function reconstructPath(Node $current): array {
        $totalPath = [];
        while ($current !== null) {
            $totalPath[] = $current;
            $current = $current->getParentNode();
        }
        return array_reverse($totalPath);
    }
    
    private function getNeighbours(Node $node): array {
        $neighbors = [];
        $offsets = [
            new Vector3(1, 0, 0), new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1), new Vector3(0, 0, -1),
            new Vector3(1, 1, 0), new Vector3(-1, 1, 0),
            new Vector3(0, 1, 1), new Vector3(0, 1, -1),
            new Vector3(1, -1, 0), new Vector3(-1, -1, 0),
            new Vector3(0, -1, 1), new Vector3(0, -1, -1)
        ];

        foreach ($offsets as $offset) {
            $neighborPos = $node->add($offset->x, $offset->y, $offset->z);
            $neighborNode = Node::fromVector3($neighborPos);
            $cost = 0;
            if ($this->getDataWorld()->couldStandAt($neighborPos, $cost) && $this->getDataWorld()->isAreaEmpty($neighborPos)) {
                $neighbors[] = $neighborNode;
            } else {
                if($offset == $offsets[array_key_last($offsets)]) error_log("Invalid neiighbor at: " . $neighborPos->__toString());
            }
        }
        return $neighbors;
    }

    private function heuristic(Node $a, Node $b): float {
        return abs($a->x - $b->x) + abs($a->y - $b->y) + abs($a->z - $b->z);
    }

    public function getDataWorld(): DataWorld {
        return $this->dataWorld;
    }
}
