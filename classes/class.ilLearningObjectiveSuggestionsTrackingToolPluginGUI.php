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

        $pl = $this->getPlugin();
        $tpl = $pl->getTemplate('tpl.tracking-tool.html');

        $userId = $DIC->user()->getId();
        $sorted = $this->sortColumns($userId);
        $finalTestsStates = ilLearnObjectFinalTestStates::getData([$userId]);
        $learningObjectives = [];

        if (count($finalTestsStates)) {
            foreach ($sorted as $sort_key => $sort_arr) {

                if (array_key_exists($sort_key, $finalTestsStates[$userId])) {
                    /** @var ilLearnObjectFinalTestState $finalTestsState */
                    $finalTestsStates_course = $finalTestsStates[$userId][$sort_key];

                    foreach ($finalTestsStates_course as $finalTestsState) {
                        $learningObjectives[$finalTestsState->getLocftestCrsObjId()] = array(
                            'txt' => $finalTestsState->getLocftestLearnObjectiveTitle(),
                            'obj_id' => $sort_arr['obj_id'],
                            'objective_id' => $sort_arr['objective_id'],
                            'default' => true,
                            'width' => 'auto',
                        );
                    }

                }
            }
        }

        $trackingToolData = [];
        $processed = array();
        $accordionLearningObjectives = [];
        if (count($finalTestsStates)) {
            foreach ($finalTestsStates[$userId] as $rec) {
                $accordionLearningObjectives[$rec[0]->getLocftestCrsObjId()] = 0;
            }
        }

        foreach ($finalTestsStates[$userId] as $finalTests) {
            foreach($finalTests as $key => $value) {
                if ($value->getLocftestCrsObjId()) {
                    // check if data already exists
                    $crsObjId = $value->getLocftestCrsObjId();
                    $crsObjectiveId = $value->getLocftestObjectiveId();
                    if (isset($processed[$crsObjId])) {
                        if (isset($processed[$crsObjId][$crsObjectiveId])) {
                            continue;
                        }
                    }

                    $trackingToolData[$value->getLocftestCrsObjId()][] = [
                        'title' => $value->getLocftestObjectiveTitle(),
                        'test_percentage' => $value->getLocftestPercentage(),
                        'test_required_percentage' => $value->getLocftestQplsRequiredPercentage(),
                        'what_is' => 1
                    ];

                    $accordionLearningObjectives[$value->getLocftestCrsObjId()] += 1;
                    $processed[$crsObjId][$crsObjectiveId] = $userId;
                }
            }
        }

        foreach($learningObjectives as $key => $learningObjective) {
            foreach($trackingToolData as $k => $data) {

                if ($key === $k) {
                    $completed = 0;
                    foreach($data as $keyCourse => $course) {
                        if ($course['test_percentage'] >= $course['test_required_percentage']) {
                            $completed ++;
                        }
                    }
                    $learningObjectives[$key]['courses'] = $data;
                    $learningObjectives[$key]['count_completed_courses'] = $completed;
                }
            }
        }

        $html = '<div class="tracking-tool">';


        foreach ($learningObjectives as $key => $learningObjective) {
            $classStatusCourses = 'completed';
            if ($learningObjective['count_completed_courses'] < count($learningObjective['courses'])) {
                $classStatusCourses = 'not-completed';
                $checkIcon = 'not-completed.svg';
            } else  {
                $checkIcon = 'passed.svg';
            }


            // TODO Remove it
           /* dd(ilCourseObjective::_lookupContainerIdByObjectiveId(96));*/
           /* $refId = new ilObjLearningModule($key, false);

            dd($refId);

            dd($learningObjectives);
            $ref_id = $DIC->ctrl()->getRequestTargetRefId(); // current ref_id*/
            /*$tree = $DIC->repositoryTree();
            $parent_course_ref_id = $tree->checkForParentType(96, 'crs'); // 'crs' is the course type

            if ($parent_course_ref_id) {
                $course_obj_id = ilObject::_lookupObjId(85);
                $course = ilObjectFactory::getInstanceByObjId($course_obj_id);
                // $course is your parent course object

                dd($course);
            }*/



            $html .= '<div class="tracking-tool-accordion-item">';
            $html .= '<img src="' . $this->pl->getDirectory() . '/templates/images/tree_col.svg" class="tracking-tool-tree-icon" data-action="expand">';
            $html .= '<span class="learning-objective-title"><a href="#">' . $learningObjective['txt'] . '</a></span>';

            $html .= '<span class="icon-check icon-check-' . $classStatusCourses . '">';
            $html .= '<img src="' . $this->pl->getDirectory() . '/templates/images/' . $checkIcon . '">';
            $html .= '</span>';
            $html .= '<span class="count-courses ' . $classStatusCourses . '-courses">' . $learningObjective['count_completed_courses'] . ' von ' . count($learningObjective['courses']) . '</span>';
            $html .= '</div>';

            $html .= '<div class="tracking-tool-panel">';
            $html .= '<div class="tracking-tool-test-required-percentage">';
            $html .= '</div>';

            foreach ($learningObjective['courses'] as $k => $course) {


                $html .= '<div class="accordion-content">' . $course['title'] . '<span class="percentage">' . ($course['test_percentage'] ?? 0) .'%</span></div>';
                $html .= '<div class="tracking-tool-progress-container">';
                $html .= '<div class="target-line" style="width: ' . $course['test_required_percentage'] . '%;"></div>';

                $classProgressBar = 'progress-bar';
                if ($course['test_percentage'] > $course['test_required_percentage']) {
                    $classProgressBar = 'progress-bar-percentage-completed';
                }

                $html .= '<div class="' . $classProgressBar . '" style="width: ' . ($course['test_percentage'] ?? 0) . '%;"></div>';
                $html .= '</div>';
            }

            $html .= '</div>';
        }




        // TODO Remove this
        /*foreach ($learningObjectives as $key => $learningObjective) {
            $html .= '<hr><hr>';
            $html .= '<div class="accordion">' . $learningObjective['txt'] . '</div>';
            $html .= '<div>ID: ' . $key . '</div>';
            $html .= '<h3>Course: ' . $key . '</h3>';

            foreach ($learningObjective['courses'] as $k => $course) {

                $html .= '<div>Title: ' . $course['title'] . '</div>';
                $html .= '<div>Test Percentage: ' . $course['test_percentage'] . '</div>';
                $html .= '<div>Test Required Percentage: ' . $course['test_required_percentage'] . '</div>';
                $html .= '<div>What Is: ' . $course['what_is'] . '</div>';
                $html .= '<hr>';
            }
        }

        $html .= '</div>';*/

/*        $tpl->setCurrentBlock('tracking_tool');*/
        $tpl->setCurrentBlock('tracking_tool');
        $tpl->setVariable('HTML', $html);
        $tpl->parseCurrentBlock();

        return $tpl->get();
    }

    private function sortColumns(int $userId): array
    {
        //First sort scores
        $scores = NewLearningObjectiveScores::getData($userId);
        //if the scores are equal, sort because of the weight value
        $weights = getFineWeights::getData();

        $sorting = array();

        foreach ($scores as $score) {

            /**
             * @var NewLearningObjectiveScore $score
             */

            if (key_exists('weight_fine_'.$score->getObjectiveId(),$weights)) {
                $fine = $weights['weight_fine_'.$score->getObjectiveId()];
            } else {
                //fallback
                $fine = 1;
            }

            $sorting[$score->getObjectiveId()] = [
                'title' => $score->getTitle(),
                'score' => $score->getScore(),
                'obj_id' => $score->getCourseObjId(),
                'objective_id' => $score->getObjectiveId(),
                'weight' => $fine
            ];
        }

        return $sorting;
    }
}

