<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 *
 * Backward-compat patch: when v1.0.x is upgraded to multi-slider support,
 * existing slides have no parent. Create a default `homepage_hero` slider
 * (assigned to All Stores) and attach every orphan slide to it.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class CreateDefaultSlider implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply(): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $sliderTable      = $this->moduleDataSetup->getTable('panth_hero_slider_slider');
        $sliderStoreTable = $this->moduleDataSetup->getTable('panth_hero_slider_slider_store');
        $slideTable       = $this->moduleDataSetup->getTable('panth_hero_slider_slide');

        // Look up existing default slider, if any.
        $sliderId = $connection->fetchOne(
            $connection->select()->from($sliderTable, 'slider_id')->where('identifier = ?', 'homepage_hero')
        );

        if (!$sliderId) {
            $connection->insert($sliderTable, [
                'identifier' => 'homepage_hero',
                'name'       => 'Home Page Hero',
                'is_active'  => 1,
            ]);
            $sliderId = (int)$connection->lastInsertId($sliderTable);
        } else {
            $sliderId = (int)$sliderId;
        }

        // Ensure All-Stores assignment row.
        $hasStoreRow = $connection->fetchOne(
            $connection->select()
                ->from($sliderStoreTable, 'store_id')
                ->where('slider_id = ?', $sliderId)
                ->where('store_id = ?', 0)
        );
        if ($hasStoreRow === false || $hasStoreRow === null) {
            $connection->insertOnDuplicate(
                $sliderStoreTable,
                ['slider_id' => $sliderId, 'store_id' => 0]
            );
        }

        // Attach orphan slides (slider_id IS NULL) to this default slider.
        $connection->update(
            $slideTable,
            ['slider_id' => $sliderId],
            ['slider_id IS NULL']
        );
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
