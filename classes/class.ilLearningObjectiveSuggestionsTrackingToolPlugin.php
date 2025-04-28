<?php

namespace LearningObjectiveSuggestionsTrackingTool\classes;

use ilPageComponentPlugin;
use ilComponentFactory;
use ilPlugin;

class LearningObjectiveSuggestionsTrackingToolPlugin extends ilPageComponentPlugin
{
    /**
     * @param $a_type
     * @return bool
     */
    public function isValidParentType($a_type): bool
    {
        return true;
    }

    /**
     * @param array  $a_properties
     * @param string $a_plugin_version
     * @param bool   $move_operation
     * @return void
     */
    public function onDelete(array $a_properties, string $a_plugin_version, bool $move_operation = false): void
    {
        // ...
    }

    public function getPluginName(): string
    {
        return LearningObjectiveSuggestionsTrackingToolConstants::PLUGIN_NAME;
    }

    public static function getInstance(): ilPlugin
    {
        global $DIC;

        /** @var ilComponentFactory $component_factory */
        $component_factory = $DIC['component.factory'];
        return $component_factory->getPlugin(LearningObjectiveSuggestionsTrackingToolConstants::PLUGIN_ID);
    }
}
