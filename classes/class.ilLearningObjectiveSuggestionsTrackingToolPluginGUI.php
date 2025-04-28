<?php

/**
 * @ilCtrl_isCalledBy ilLearningObjectiveSuggestionsTrackingToolPluginGUI: ilPCPluggedGUI
 */
class ilLearningObjectiveSuggestionsTrackingToolPluginGUI extends ilPageComponentPluginGUI
{
    private ilGlobalTemplateInterface $tpl;

    private ilSetting $settings;

    private $ctrl;

    private $pl;

    public function __construct()
    {
        global $DIC;

        parent::__construct();
        $this->ctrl = $DIC->ctrl();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->pl = ilLearningObjectiveSuggestionsTrackingToolPlugin::getInstance();
        $this->lng = $DIC->language();
        $this->settings = new ilSetting($this->pl->getPluginName());
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

        $cmds = [
            'create',
            'update',
            'edit',
            'cancel'
        ];
        if (in_array($cmd, $cmds)) {
            $this->$cmd();
        }
    }

    /**
     * Override Method of ilPageComponentPluginGUI()
     * Generates the creation dialog (opening config for the first time)
     *
     * @return void
     * @throws ilCtrlException
     */
    public function insert(): void
    {
        global $DIC;

        $DIC->ctrl()->redirectByClass(self::class, 'create');
        //$this->create();
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
        /*$this->save(true);*/


        $properties = [];
        if ($this->createElement($properties)) {
            $this->tpl->setOnScreenMessage("success", "Dashboard wurde angelegt", true);
            $this->returnToParent();
        }

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
     * @throws \ilTemplateException
     * @throws ilSystemStyleException
     */
    function getElementHTML($a_mode, array $a_properties, $plugin_version): string
    {
        global $DIC;

        if ($DIC->user()->isAnonymous()) {
            return '';
        }

        //$tpl = $this->pl->getTemplate('tpl.tracking-tool.html');

        $pl = $this->getPlugin();
        $tpl = $this->pl->getTemplate('tpl.tracking-tool.html');

       /* $this->tpl = new \ilTemplate(
            'Customizing/global/plugins/Services/COPage/PageComponent/SemanticNetwork/templates/tpl.semantic-network.html',
            true,
            true,
        );*/

        /*$this->tpl = new ilGlobalTemplate(
            'tpl.tracking-tool.html',
            true,
            true,
            'Customizing/global/plugins/Services/COPage/PageComponent/LearningObjectiveSuggestionsTrackingTool',
        );*/



        $tpl->setCurrentBlock('test');
        /*$tpl->setVariable("ARIA_PRESSED", $aria_pressed);*/
        //$this->tpl->parseCurrentBlock();

        $tpl->parseCurrentBlock();
        dd($tpl->get());

        return $tpl->get();
    }
}

