<?php

/**
 * @ilCtrl_isCalledBy ilLearningObjectiveSuggestionsTrackingToolPluginGUI: ilPCPluggedGUI
 */
class ilLearningObjectiveSuggestionsTrackingToolPluginGUI extends ilPageComponentPluginGUI
{
    private $tpl;

    private ilCtrlInterface $ctrl;

    private ilPlugin $pl;

    public function __construct()
    {
        global $DIC;

        parent::__construct();
        $this->ctrl = $DIC->ctrl();
        $this->pl = ilLearningObjectiveSuggestionsTrackingToolPlugin::getInstance();
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

        $commands = [
            'create',
            'update',
            'edit',
            'cancel'
        ];
        if (in_array($cmd, $commands)) {
            $this->$cmd();
        }
    }

    /**
     * @return void
     * @throws ilCtrlException
     */
    public function insert(): void
    {
        global $DIC;

        $DIC->ctrl()->redirectByClass(self::class, 'create');
    }

    /**
     * Override Method of ilPageComponentPluginGUI()
     * Creating the editing dialog (opening config after the first time)
     *
     * @return void
     */
    public function edit(): void
    {
    }

    /**
     * @return void
     */
    public function create(): void
    {
        global $DIC;

        $properties = [];
        if ($this->createElement($properties)) {
            $tpl = $DIC->ui()->mainTemplate();
            $tpl->setOnScreenMessage('success', 'Tracking Tool wurde angelegt', true);
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
     * @throws ilTemplateException
     * @throws ilCtrlException
     */
    function getElementHTML($a_mode, array $a_properties, $plugin_version): string
    {
        global $DIC;

        if ($DIC->user()->isAnonymous()) {
            return '';
        }

        $pl = $this->getPlugin();
        $this->tpl = $pl->getTemplate('tpl.tracking-tool.html');

        $userId = $DIC->user()->getId();
        $sorted = $this->sortByScore($userId);
        $finalTestsStates = ilLearnObjectFinalTestStates::getData([$userId]);
        $learningObjectives = [];

        if (count($finalTestsStates)) {
            $learningObjectives = $this->getLearningObjectives($sorted, $finalTestsStates[$userId]);
        }

        $trackingToolData = $this->getTrackingToolData($finalTestsStates, $userId);
        $learningObjectives = $this->storeCoursesInLearningObjectives($learningObjectives, $trackingToolData);
        $this->buildAccordionHtml($learningObjectives);

        return $this->tpl->get();
    }

    /**
     * @param array $finalTestsStates
     * @param int   $userId
     * @return array
     */
    private function getTrackingToolData(array $finalTestsStates, int $userId): array
    {
        $trackingToolData = [];
        $processed = [];

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
                    $processed[$crsObjId][$crsObjectiveId] = $userId;
                }
            }
        }
        return $trackingToolData;
    }

    /**
     * @param array $sorted
     * @param array $finalTestsStatesUser
     * @return array
     */
    private function getLearningObjectives(array $sorted, array $finalTestsStatesUser): array
    {
        $learningObjectives = [];
        foreach ($sorted as $sort_key => $sort_arr) {

            if (array_key_exists($sort_key, $finalTestsStatesUser)) {
                /** @var ilLearnObjectFinalTestState $finalTestsState */
                $finalTestsStates_course = $finalTestsStatesUser[$sort_key];

                foreach ($finalTestsStates_course as $finalTestsState) {
                    $learningObjectives[$finalTestsState->getLocftestCrsObjId()] = array(
                        'txt' => $finalTestsState->getLocftestLearnObjectiveTitle(),
                        'obj_id' => $sort_arr['obj_id'],
                        'objective_id' => $sort_arr['objective_id'],
                        'default' => true,
                        'score' => $sort_arr['score'],
                        'width' => 'auto',
                    );
                }
            }
        }
        return $learningObjectives;
    }

    /**
     * @param array $learningObjectives
     * @param array $trackingToolData
     * @return array
     */
    private function storeCoursesInLearningObjectives(array $learningObjectives, array $trackingToolData): array
    {
        foreach($learningObjectives as $key => $learningObjective) {
            foreach($trackingToolData as $k => $data) {

                if ($key === $k) {
                    $completed = 0;
                    foreach($data as $keyCourse => $course) {
                        if ($course['test_percentage'] !== null && $course['test_percentage'] >= $course['test_required_percentage']) {
                            $completed ++;
                        }
                    }
                    $learningObjectives[$key]['courses'] = $data;
                    $learningObjectives[$key]['count_completed_courses'] = $completed;
                }
            }
        }
        return $learningObjectives;
    }

    /**
     * @param array $learningObjectives
     * @return void
     * @throws ilCtrlException
     */
    private function buildAccordionHtml(array $learningObjectives): void
    {
        $html = '<div class="tracking-tool">';

        $index = 1;
        $maxWeight = 0;
        foreach ($learningObjectives as $key => $learningObjective) {
            if ($index === 1) {
                $maxWeight = $learningObjective['score'];
            }

            $classStatusCourses = 'completed';
            if ($learningObjective['count_completed_courses'] < count($learningObjective['courses'])) {
                $classStatusCourses = 'not-completed';
                $checkIcon = 'not-completed.svg';
            } else  {
                $checkIcon = 'passed.svg';
            }

            $refId = $this->getCourseRefId($learningObjective['obj_id']);
            $this->setRefIdAsClassParameter($refId);
            $courseLink = $this->getCourseLink();

            $countWeightSymbols = 3;
            if ($learningObjective['score'] < $maxWeight) {
                $countWeightSymbols = 2;
            }

            $htmlIconsAlert = '';
            for ($i = 1; $i <= $countWeightSymbols; $i++) {
                $htmlIconsAlert .= '<img src="' . $this->pl->getDirectory() . '/templates/images/alert.svg" class="icon-weight">';
            }

            if ($learningObjective['score'] < $maxWeight) {
                $htmlIconsAlert .= '<img src="' . $this->pl->getDirectory() . '/templates/images/alert-secondary.svg" class="icon-weight">';
            }

            $html .= '<div class="tracking-tool-accordion-item">';
            $html .= '<img src="' . $this->pl->getDirectory() . '/templates/images/tree_col.svg" class="tracking-tool-tree-icon" data-action="expand">';
            $html .= '<span class="learning-objective-title"><a href="' . $courseLink . '">' . $learningObjective['txt'] . '</a></span>';

            $html .= '<span class="icon-check icon-check-' . $classStatusCourses . '">';
            $html .= '<img src="' . $this->pl->getDirectory() . '/templates/images/' . $checkIcon . '">';
            $html .= '</span>';
            $html .= '<span class="count-courses ' . $classStatusCourses . '-courses">' . $learningObjective['count_completed_courses'] . ' von ' . count($learningObjective['courses']) . '</span>';
            $html .= '<div class="container-weight">';
            $html .= '<span class="weight">' . $htmlIconsAlert . '</span>';
            $html .= '</div>';
            $html .= '</div>';

            $html .= '<div class="tracking-tool-panel">';
            $html .= '<div class="tracking-tool-test-required-percentage">';
            $html .= '</div>';
            $html .= $this->buildAccordionDropdownHtml($learningObjective['courses']);
            $html .= '</div>';

            $index++;
        }
        $html .= '</div>';
        $this->setTemplateBlock($html);
    }

    /**
     * @param array $learningObjectiveCourses
     * @return string
     */
    private function buildAccordionDropdownHtml(array $learningObjectiveCourses): string
    {
        $html = '';
        foreach ($learningObjectiveCourses as $k => $course) {
            $html .= '<div class="percent-line" style="width: ' . ($course['test_required_percentage'] ?? '') . '%;">';
            $html .= '<div class="percent-line-percent"><span>' . $course['test_required_percentage'] . '</span></div>';
            $html .= '<div class="line"><div></div></div>';
            $html .= '</div>';
            $html .= '<div class="accordion-content">' . $course['title'] . '<span class="percentage">' . ($course['test_percentage'] ?? 0) . '%</span></div>';
            $html .= '<div class="tracking-tool-progress-container">';

            $targetLineClass = 'target-line';

            if ($course['test_percentage'] >= $course['test_required_percentage']) {
                $targetLineClass .= '-reached';
            }
            $html .= '<div class="' . $targetLineClass . '" style="width: ' . ($course['test_required_percentage'] ?? '') . '%;"></div>';

            $classProgressBar = 'progress-bar';
            if ($course['test_percentage'] !== null && $course['test_percentage'] >= $course['test_required_percentage']) {
                $classProgressBar = 'progress-bar-percentage-completed';
            }

            $html .= '<div class="' . $classProgressBar . '" style="width: ' . ($course['test_percentage'] ?? 0) . '%;"></div>';
            $html .= '</div>';
        }
        return $html;
    }

    /**
     * @param int $crsObjectId
     * @return int|mixed
     */
    private function getCourseRefId(int $crsObjectId): mixed
    {
        $references = ilObject::_getAllReferences($crsObjectId);

        // Return the first reference ID if available, otherwise return 0
        return !empty($references) ? array_values($references)[0] : 0;
    }

    /**
     * @throws ilCtrlException
     */
    private function getCourseLink(): string
    {
        return $this->ctrl->getLinkTargetByClass(ilRepositoryGUI::class);
    }

    /**
     * @param $refId
     * @return void
     * @throws ilCtrlException
     */
    private function setRefIdAsClassParameter($refId): void
    {
        $this->ctrl->setParameterByClass(
            'ilrepositorygui',
            'ref_id', $refId
        );
    }

    /**
     * @param string $html
     * @return void
     */
    private function setTemplateBlock(string $html): void
    {
        $this->tpl->setCurrentBlock('tracking_tool');
        $this->setVariables($html);
        $this->tpl->parseCurrentBlock();
    }

    /**
     * @param string $html
     * @return void
     */
    private function setVariables(string $html): void
    {
        $this->tpl->setVariable('TITLE', $this->pl->txt('title'));
        $this->tpl->setVariable('SUBTITLE', $this->pl->txt('subtitle'));
        $this->tpl->setVariable('DESCRIPTION', $this->pl->txt('description'));
        $this->tpl->setVariable('LEARNING_SUGGESTION', $this->pl->txt('learning_suggestion') . ' <span class="lets-get-started">' . $this->pl->txt('lets_get_started') . '</span>');
        $this->tpl->setVariable('HTML', $html);
    }

    /**
     * @param int $userId
     * @return array
     */
    private function sortByScore(int $userId): array
    {
        $scores = NewLearningObjectiveScores::getData($userId);
        $weights = getFineWeights::getData();

        $sorting = [];
        foreach ($scores as $score) {

            $fine = 1;
            /**
             * @var NewLearningObjectiveScore $score
             */
            if (key_exists('weight_fine_'.$score->getObjectiveId(),$weights)) {
                $fine = $weights['weight_fine_'.$score->getObjectiveId()];
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

