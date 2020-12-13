<?php
declare(strict_types=1);

namespace shock95x\auctionhouse\menu;

use DateTime;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use shock95x\auctionhouse\database\DataHolder;
use shock95x\auctionhouse\event\AuctionEndEvent;
use shock95x\auctionhouse\utils\Locale;
use shock95x\auctionhouse\utils\Settings;
use shock95x\auctionhouse\utils\Utils;

class ListingsMenu extends AHMenu {

	public function __construct(Player $player, bool $returnMain = true, int $page = 1) {
		$this->setName(Locale::getMessage($player, "listings-menu-name"));
		$this->page = $page;
		parent::__construct($player, $returnMain, true);
	}

	public function setItems(int $page, int $max, int $total) : void {
		$info = Utils::getButtonItem($this->getPlayer(), "info", "listings-description");

		$stats = Utils::getButtonItem($this->getPlayer(), "stats", "listings-stats");
		$stats->setLore(str_replace(["%page%", "%max%", "%total%"], [$page, $max, $total], $stats->getLore()));

		$this->getInventory()->setItem(53, $info);
		$this->getInventory()->setItem(49, $stats);
	}

	public function renderItems(): void {
        $total = count(DataHolder::getListingsByPlayer($this->getPlayer()));
        $max = 0;
        for($i = 0; $i < $total; $i += 45) $max++;
        if($max == 0) $max = 1;

        $this->page < 1 ? $this->page = 1 : $this->page;
        $start = ($this->page - 1) * 45;
        $listings = array_slice(DataHolder::getListingsByPlayer($this->getPlayer()), $start, 45);

        if($this->page > $max) {
            $this->page = 1;
            $this->renderItems();
            return;
        }

        foreach($listings as $key => $auction) {
			$item = clone $auction->getItem();
			$tag = $item->hasCompoundTag() ? $item->getNamedTag() : new CompoundTag();
			$tag->setLong("marketId", $auction->getMarketId());

			$endTime = (new DateTime())->diff((new DateTime())->setTimestamp($auction->getEndTime()));
			$listedItem = Locale::getMessage($this->getPlayer(), "your-listed-item");

			$lore = str_replace(["%price%", "{H}", "{M}"], [$auction->getPrice(true, Settings::formatPrice()), ($endTime->days * 24 + $endTime->h), $endTime->i], preg_filter('/^/', TextFormat::RESET, $listedItem));
			$lore = Settings::allowLore() ? array_merge($item->getLore(), $lore) : $lore;
			$item->setNamedTag($tag)->setCustomName(TextFormat::RESET . $item->getName())->setLore($lore);

	        $this->getInventory()->setItem($key, $item);
		}
        for($i = count($listings); $i < 45; ++$i) {
	        $this->getInventory()->setItem($i, Item::get(Item::AIR));
        }
		$this->setItems($this->page, $max, $total);
	}

	public function handle(Player $player, Item $itemClicked, Inventory $inventory, int $slot): bool {
		if($slot <= 44 && $itemClicked->getNamedTag()->hasTag("marketId") && $itemClicked->getId() !== Item::AIR) {
			$auction = DataHolder::getListingById($itemClicked->getNamedTag()->getLong("marketId"));
			if($auction == null) {
				return false;
			}
			DataHolder::setExpired($auction);
			(new AuctionEndEvent($auction, AuctionEndEvent::CANCELLED, $player))->call();
			$inventory->removeItem($itemClicked);
		}
		return parent::handle($player, $itemClicked, $inventory, $slot);
	}

	public function show(Player $player): void {
		Utils::setViewingMenu($player, Utils::LISTINGS_MENU);
		parent::show($player);
	}
}