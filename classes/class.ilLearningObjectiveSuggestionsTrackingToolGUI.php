<?php

namespace LearningObjectiveSuggestionsTrackingTool\classes;

use ilGlobalTemplateInterface;
use ilPageComponentPluginGUI;

/**
 * @ilCtrl_isCalledBy LearningObjectiveSuggestionsTrackingToolGUI: ilPCPluggedGUI
 */
class LearningObjectiveSuggestionsTrackingToolGUI extends ilPageComponentPluginGUI
{
    private ilGlobalTemplateInterface $tpl;

    private LearningObjectiveSuggestionsTrackingToolPlugin $pl;

    public function __construct()
    {
        global $DIC;
        parent::__construct();
        global $DIC;
        $this->ctrl = $DIC->ctrl();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->pl = LearningObjectiveSuggestionsTrackingToolPlugin::getInstance();
        $this->lng = $DIC->language();
    }

    /**
     * Commands
     *
     * @return void
     */
    public function executeCommand(): void
    {
        global $ilCtrl;

        $cmd = $ilCtrl->getCmd();

        if (in_array($cmd, [
            "create",
            "update",
            "edit",
            "cancel"
        ])
        ) {
            $this->$cmd();
        }
    }

    /**
     * Override Method of ilPageComponentPluginGUI()
     * Generates the creation dialog (opening config for the first time)
     *
     * @return void
     */
    public function insert(): void
    {
        global $tpl;
    }

    /**
     * Override Method of ilPageComponentPluginGUI()
     * Creating the editing dialog (opening config after the first time)
     *
     * @return void
     */
    public function edit(): void
    {
        global $tpl;
    }

    /**
     * Override Method of ilPageComponentPluginGUI()
     * Save config form
     *
     * @return void
     */
    public function create(): void
    {
        $this->save(true);
    }

    /**
     * Override Method of ilPageComponentPluginGUI()
     *
     * Update config (save Form)
     *
     * @return void
     */
    public function update(): void
    {
        $this->save();
    }

    /**
     * Cancel button - cancel editing
     */
    function cancel(): void
    {
        $this->returnToParent();
    }

    /**
     * @param bool $create
     * @return void
     */
    protected function save(bool $create = false): void
    {
    }

    /**
     * HTML Content
     *
     * @param       $a_mode
     * @param array $a_properties
     * @param       $plugin_version
     * @return string
     */
    function getElementHTML($a_mode, array $a_properties, $plugin_version): string
    {
        global $DIC;

        if ($DIC->user()->isAnonymous()) {
            return '';
        }

        $calendar_activated = $a_properties["calendar_activated"];
        $mail_activated = $a_properties["mail_activated"];
        $tasks_activated = $a_properties["tasks_activated"];

        $tpl = $this->getPlugin()->getTemplate("tpl.content.html");

        if ($calendar_activated) {
            $calendar = $this->getCalendarElement();
            $tpl->setVariable("CALENDAR", $calendar);
        }

        if ($mail_activated) {
            $mail = $this->getMailElement();
            $tpl->setVariable("MAIL", $mail);
        }

        if ($tasks_activated) {
            $tasks = $this->getTasksElement();
            $tpl->setVariable("TASKS", $tasks);
        }

        if ($a_mode === 'edit') {
            $this->tpl->addInlineCss(".kpg_calendar_component {pointer-events: none;}");
        }

        return $tpl->get();
    }
}

