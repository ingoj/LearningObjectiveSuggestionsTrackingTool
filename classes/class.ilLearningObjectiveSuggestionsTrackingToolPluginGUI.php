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
    public function cancel(): void
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
    public function getElementHTML($a_mode, array $a_properties, $plugin_version): string
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

        $learnObjectSuggestResults = ilLearnObjectSuggResults::getData([$userId]);


        $requiredPercentages = [];
        foreach ($finalTestsStates as $key => $finalTestState) {
            foreach ($finalTestState as $k => $value) {
                $masterCrsId = $value[0]->getLocftestMasterCrsId();
                $requiredPercentages[$masterCrsId] = 60;
                //$requiredPercentages[$masterCrsId] = $learnObjectSuggestResults[$userId]->getAveragePercentage(ilParticipationCertificateConfig::getConfig('calculation_type_processing_state_suggested_objectives', $masterCrsId));
            }
        }

        if (count($finalTestsStates)) {
            $learningObjectives = $this->getLearningObjectives($sorted, $finalTestsStates[$userId]);
        }

        $trackingToolData = $this->getTrackingToolData($finalTestsStates, $userId);

        $learningObjectives = $this->storeCoursesInLearningObjectives(
            $learningObjectives,
            $trackingToolData,
            $requiredPercentages
        );

        $this->buildAccordionHtml($learningObjectives, $userId);

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
            foreach ($finalTests as $key => $value) {
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
                        'suggested' => $sort_arr['suggested'],
                    );
                }
            }
        }
        return $learningObjectives;
    }

    /**
     * @param array $learningObjectives
     * @param array $trackingToolData
     * @param array $requiredPercentages
     * @return array
     */
    private function storeCoursesInLearningObjectives(
        array $learningObjectives,
        array $trackingToolData,
        array $requiredPercentages
    ): array {
        foreach ($learningObjectives as $key => $learningObjective) {
            foreach ($trackingToolData as $k => $data) {

                if ($key === $k) {
                    $completed = 0;
                    foreach ($data as $course) {
                        if ($course['test_percentage'] !== null && $course['test_percentage'] >= '60') {
                            $completed++;
                        }
                    }

                    $learningObjectives[$key]['required_percentage'] = $requiredPercentages[$learningObjective['obj_id']];
                    $learningObjectives[$key]['courses'] = $data;
                    $learningObjectives[$key]['count_completed_courses'] = $completed;
                }
            }
        }

        return $learningObjectives;
    }

    /**
     * @param array $learningObjectives
     * @param int   $userId
     * @return void
     * @throws ilCtrlException
     */
    private function buildAccordionHtml(array $learningObjectives, int $userId): void
    {
        global $DIC;

        $html = '<div class="tracking-tool">';
        $index = 1;
        $scores = array_column($learningObjectives, 'score');

        if (!empty($scores)) {
            $maxScore = max($scores);
            $minScore = min($scores);
        }

        $navigationHistory = $DIC['ilNavigationHistory']->getItems();

        $lastVisitedObjId = null;
        foreach ($navigationHistory as $historyItem) {
            $historyItemObjectId = ilObject::_lookupObjectId($historyItem['ref_id']);
            foreach ($learningObjectives as $key => $learningObjective) {
                if ($historyItem['type'] === 'crs' &&
                    $historyItemObjectId == $key
                ) {
                    $lastVisitedObjId = $historyItemObjectId;
                    break;
                }
            }

            if (!empty($lastVisitedObjId)) {
                break;
            }
        }

        foreach ($learningObjectives as $learningObjectiveObjId => $learningObjective) {
            $classStatusCourses = 'completed';
            if ($learningObjective['count_completed_courses'] < count($learningObjective['courses'])) {
                $classStatusCourses = 'not-completed';
                $checkIcon = 'not-completed.svg';
            } else {
                $checkIcon = 'passed.svg';
            }

            $refId = $this->getCourseRefId($learningObjectiveObjId);
            $this->setRefIdAsClassParameter($refId);
            $courseLink = $this->getCourseLink();

            $countWeightSymbols = 0;
            if (!empty($maxScore) && $learningObjective['suggested'] && $learningObjective['score'] === $maxScore) {
                $countWeightSymbols = 3;
            } elseif (!empty($minScore) && $learningObjective['suggested'] && $learningObjective['score'] === $minScore) {
                $countWeightSymbols = 1;
            } elseif (!empty($maxScore) && !empty($minScore) && $learningObjective['suggested'] && $learningObjective['score'] < $maxScore && $learningObjective['score'] > $minScore) {
                $countWeightSymbols = 2;
            }

            $htmlIconsAlert = '';
            for ($i = 1; $i <= $countWeightSymbols; $i++) {

                if ($learningObjective['count_completed_courses'] === count($learningObjective['courses'])) {
                    $htmlIconsAlert .= '<img src="' . $this->pl->getDirectory() . '/templates/images/alert-completed.svg" class="icon-weight">';
                } else {
                    $htmlIconsAlert .= '<img src="' . $this->pl->getDirectory() . '/templates/images/alert.svg" class="icon-weight">';
                }
            }

            if ($countWeightSymbols === 1) {
                $htmlIconsAlert .= '<img src="' . $this->pl->getDirectory() . '/templates/images/alert-secondary.svg" class="icon-weight">';
                $htmlIconsAlert .= '<img src="' . $this->pl->getDirectory() . '/templates/images/alert-secondary.svg" class="icon-weight">';
            } elseif ($countWeightSymbols === 2) {
                $htmlIconsAlert .= '<img src="' . $this->pl->getDirectory() . '/templates/images/alert-secondary.svg" class="icon-weight">';
            }

            $html .= '<div class="tracking-tool-accordion-item">';
            if ($learningObjective['suggested']) {
                $html .= '<img src="' . $this->pl->getDirectory() . '/templates/images/tree_col.svg" class="tracking-tool-tree-icon tracking-tool-active" data-action="collapse">';
            } else {
                $html .= '<img src="' . $this->pl->getDirectory() . '/templates/images/tree_col.svg" class="tracking-tool-tree-icon" data-action="expand">';
            }
            $html .= '<span class="learning-objective-title">';
            $html .= '<a href="' . $courseLink . '">' . $learningObjective['txt'] . '</a>';
            $html .= '</span>';

            if ($lastVisitedObjId == $learningObjectiveObjId) {
                $html .= '<span class="last-visited triangle"></span>';
            }

            $html .= '<span class="icon-check icon-check-' . $classStatusCourses . '">';
            $html .= '<img src="' . $this->pl->getDirectory() . '/templates/images/' . $checkIcon . '">';
            $html .= '</span>';

            $html .= '<span class="count-courses ' . $classStatusCourses . '-courses">' . $learningObjective['count_completed_courses'] . ' von ' . count($learningObjective['courses']) . '</span>';
            $html .= '<div class="container-weight">';
            $html .= '<span class="weight">' . $htmlIconsAlert . '</span>';
            $html .= '</div>';
            $html .= '</div>';

            $html .= '<div class="container-percent-line">';
            $html .= '<div class="percent-line" style="width: ' . ($learningObjective['required_percentage'] ?? 0) . '%;">';
            $html .= '<div class="percent-line-percent"><span>' . ($learningObjective['required_percentage'] ?? 0) . '%</span></div>';
            $html .= '<div class="line">';
            $html .= '<div></div>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';

            $html .= '<div class="tracking-tool-panel" id="tracking-tool-panel-' . $learningObjectiveObjId . '-' . $userId . '">';
            $html .= '<div class="tracking-tool-test-required-percentage">';
            $html .= '</div>';
            $html .= $this->buildAccordionDropdownHtml($learningObjective['courses'], $learningObjective['required_percentage']);

            $html .= '<div class="percent-line" style="width: ' . ($learningObjective['required_percentage'] ?? 0) . '%;">';
            $html .= '<div class="percent-line-percent"><span></span></div>';
            $html .= '<div class="line">';
            $html .= '<div></div>';
            $html .= '</div>';
            $html .= '</div>';


            $html .= '</div>';

            $index++;
        }
        $html .= '</div>';
        $this->setTemplateBlock($html);
    }

    /**
     * @param array    $learningObjectiveCourses
     * @param int|null $requiredPercentage
     * @return string
     */
    private function buildAccordionDropdownHtml(
        array $learningObjectiveCourses,
        ?int $requiredPercentage = null
    ): string {
        $html = '';
        foreach ($learningObjectiveCourses as $k => $course) {
            $html .= '<div class="accordion-content">' . $course['title'] . '<span class="percentage">' . ($course['test_percentage'] ?? 0) . '%</span></div>';
            $html .= '<div class="tracking-tool-progress-container">';

            $targetLineClass = 'target-line';

            if ($course['test_percentage'] >= $requiredPercentage) {
                $targetLineClass .= '-reached';
            }

            if ($requiredPercentage > 0) {
                $html .= '<div class="' . $targetLineClass . '" style="width: ' . $requiredPercentage . '%;"></div>';
            } else {
                $html .= '<div class="' . $targetLineClass . '"></div>';
            }

            $classProgressBar = 'progress-bar';
            if ($course['test_percentage'] !== null && $course['test_percentage'] >= $requiredPercentage) {
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
            'ref_id',
            $refId
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

        $this->tpl->setVariable('LEGENDS_TEXT_LEGENDS', $this->pl->txt('legends_text_legends'));
        $this->tpl->setVariable('LEGENDS_TEXT_SOS', $this->pl->txt('legends_text_sos'));
        $this->tpl->setVariable('LEGENDS_TEXT_SOS_2', $this->pl->txt('legends_text_sos_2'));
        $this->tpl->setVariable('LEGENDS_TEXT_LAST_VISIT', $this->pl->txt('legends_text_last_visit'));
        $this->tpl->setVariable('LEGENDS_TEXT_LAST_VISIT_2', $this->pl->txt('legends_text_last_visit_2'));
        $this->tpl->setVariable('LEGENDS_TEXT_PERCENT', $this->pl->txt('legends_text_percent'));
        $this->tpl->setVariable('LEGENDS_TEXT_PERCENT_2', $this->pl->txt('legends_text_percent_2'));
        $this->tpl->setVariable('LEGENDS_TEXT_PERCENT_3', $this->pl->txt('legends_text_percent_3'));
        $this->tpl->setVariable('LEGENDS_TEXT_COMPLETED', $this->pl->txt('legends_text_completed'));
        $this->tpl->setVariable('LEGENDS_TEXT_COMPLETED_2', $this->pl->txt('legends_text_completed_2'));


    }

    /**
     * @param int $userId
     * @return array
     */
    private function sortByScore(int $userId): array
    {
        $scores = NewLearningObjectiveScores::getData($userId);
        $weights = getFineWeights::getData();
        $suggs = getLearnSuggs::getData($userId);
        $sorting = [];
        foreach ($scores as $score) {

            $fine = 1;
            /**
             * @var NewLearningObjectiveScore $score
             */
            if (key_exists('weight_fine_' . $score->getObjectiveId(), $weights)) {
                $fine = $weights['weight_fine_' . $score->getObjectiveId()];
            }
            $suggested = false;
            foreach ($suggs as $sugg) {
                /**
                 * @var getLearnSugg $sugg
                 */
                if ($score->getObjectiveId() == $sugg->getSuggObjectiveId()) {
                    $suggested = true;
                    break;
                }
            }
            $sorting[$score->getObjectiveId()] = [
                'title' => $score->getTitle(),
                'score' => $score->getScore(),
                'obj_id' => $score->getCourseObjId(),
                'objective_id' => $score->getObjectiveId(),
                'weight' => $fine,
                'suggested' => $suggested
            ];
        }
        return $sorting;
    }
}
