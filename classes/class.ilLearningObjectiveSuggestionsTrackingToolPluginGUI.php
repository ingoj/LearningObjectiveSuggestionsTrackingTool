<?php

use ILIAS\UI\Component\Input\Container\Form\Standard;

/**
 * @ilCtrl_isCalledBy ilLearningObjectiveSuggestionsTrackingToolPluginGUI: ilPCPluggedGUI
 */
class ilLearningObjectiveSuggestionsTrackingToolPluginGUI extends ilPageComponentPluginGUI
{
    private $tpl;

    private ilCtrlInterface $ctrl;

    private ilPlugin $pl;

    private \ILIAS\UI\Factory $factory;

    private \ILIAS\UI\Renderer $renderer;

    public function __construct()
    {
        global $DIC;

        parent::__construct();
        $this->ctrl = $DIC->ctrl();
        $this->pl = ilLearningObjectiveSuggestionsTrackingToolPlugin::getInstance();
        $this->lng = $DIC->language();
        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
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
            'cancel',
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
     * @throws ilCtrlException
     * @throws ilFormException
     */
    public function edit(): void
    {
        global $DIC;

        $form = $this->buildConfigForm();
        $DIC->ui()->mainTemplate()->setContent($this->renderer->render($form));
    }

    /**
     * @throws ilCtrlException
     */
    public function buildConfigForm(): Standard
    {
        global $DIC;

        $properties = $this->getProperties();

        $field = $this->factory->input()->field();
        $input_ref_id = $field->text($this->plugin->txt('ref_id'))
                              ->withValue($properties['ref_id'] ?? '');

        $form = $this->factory->input()->container()->form()->standard(
            $DIC->ctrl()->getFormAction($this, 'update'),
            [
                'ref_id' => $input_ref_id
            ]
        );

        return $form;
    }

    /**
     * @return void
     * @throws ilCtrlException
     * @throws ilFormException
     */
    private function saveConfig(): void
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
     * @throws ilCtrlException
     */
    public function update(): void
    {
        global $DIC;

        $tpl = $DIC->ui()->mainTemplate();
        $form = $this->buildConfigForm();
        $formRequest = $form->withRequest($DIC->http()->request());

        $refId = (int) $formRequest->getData()['ref_id'];

        if ($form->getError()) {
            $this->tpl->setOnScreenMessage('failure', $this->plugin->txt('error'));
            $this->returnToParent();
        }

        if ($refId === 0) {
            $tpl->setOnScreenMessage('failure', $this->plugin->txt('updated_failure'), true);
            $this->returnToParent();
        }
        $properties = $this->getProperties();
        $properties['ref_id'] = $refId;

        if ($this->updateElement($properties)) {
            $tpl->setOnScreenMessage('success', $this->plugin->txt('updated_success'), true);
            $this->returnToParent();
        }
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
     * @throws ilCtrlException
     * @throws ilFormException
     */
   /* protected function save(bool $create = false): void
    {
    }*/

    /**
     * HTML Content
     *
     * @param       $a_mode
     * @param array $a_properties
     * @param       $plugin_version
     * @return string
     * @throws ilTemplateException
     * @throws ilCtrlException
     * @throws ilSystemStyleException
     */
    public function getElementHTML($a_mode, array $a_properties, $plugin_version): string
    {
        global $DIC;

        if ($DIC->user()->isAnonymous()) {
            return '';
        }

        $template = $DIC->ui()->mainTemplate();
        $template->addCss(ilLearningObjectiveSuggestionsTrackingToolPlugin::PLUGIN_DIRECTORY . '/templates/css/tracking-tool.css');
        $template->addJavaScript(ilLearningObjectiveSuggestionsTrackingToolPlugin::PLUGIN_DIRECTORY . '/templates/js/tracking-tool.js');

        $this->tpl = new ilTemplate(
            'tpl.tracking-tool.html',
            true,
            true,
            'public/' . ilLearningObjectiveSuggestionsTrackingToolPlugin::PLUGIN_DIRECTORY,
            ilGlobalTemplateInterface::DEFAULT_BLOCK,
            true
        );

        $userId = $DIC->user()->getId();
        $sorted = $this->sortByScore($userId);

        $learningObjectives = [];
        if (!empty($a_properties['ref_id'])) {
            $finalTestsStates = self::getData($a_properties['ref_id'], [$userId]);


            $learnObjectSuggestResults = ilLearnObjectSuggResults::getData([$userId]);

            $requiredPercentages = [];
            foreach ($finalTestsStates as $key => $finalTestState) {
                foreach ($finalTestState as $k => $value) {
                    $masterCrsId = $value[0]->getLocftestMasterCrsId();
                    $requiredPercentages[$masterCrsId] = 60;
                }
            }

            if (count($finalTestsStates)) {
                $learningObjectives = $this->getLearningObjectives($sorted, $finalTestsStates[$userId]);
            }


            if( !empty($finalTestsStates[$userId])) {
                $trackingToolData = $this->getTrackingToolData($finalTestsStates, $userId);

                $learningObjectives = $this->storeCoursesInLearningObjectives(
                    $learningObjectives,
                    $trackingToolData,
                    $requiredPercentages
                );
            }
        }

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
                if ($value->getLocftestCrsObjId()/* && $value->getLocftestCrsObjId() === 310*/) {
                    // check if data already exists
                    $crsObjId = $value->getLocftestCrsObjId();
                    $crsObjectiveId = $value->getLocftestObjectiveId();
                    if (isset($processed[$crsObjId])) {
                        if (isset($processed[$crsObjId][$crsObjectiveId])) {
                            continue;
                        }
                    }

                    $trackingToolData[$value->getLocftestCrsObjId()][] = [
                        'master_crs_id' => $value->getLocftestMasterCrsId(),
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
        $suggestedKeys = array_keys(array_filter($learningObjectives, function ($target) {
            return !empty($target['suggested']) && $target['suggested'] === true;
        }));
        $totalSuggestions = count($suggestedKeys);

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

            if (($suggestedIndex = array_search($learningObjectiveObjId, $suggestedKeys)) !== false) {
                if ($suggestedIndex === 0) {
                    $countWeightSymbols = 3;
                } elseif ($suggestedIndex === $totalSuggestions - 1) {
                    $countWeightSymbols = 1;
                } else {
                    $countWeightSymbols = 2;
                }
            } else {
                $countWeightSymbols = 0;
            }

            $htmlIconsAlert = '';
            for ($i = 1; $i <= $countWeightSymbols; $i++) {

                if ($learningObjective['count_completed_courses'] === count($learningObjective['courses'])) {
                    $htmlIconsAlert .= '<img src="' . ilLearningObjectiveSuggestionsTrackingToolPlugin::PLUGIN_DIRECTORY . '/templates/images/alert-completed.svg" class="icon-weight">';
                } else {
                    $htmlIconsAlert .= '<img src="' . ilLearningObjectiveSuggestionsTrackingToolPlugin::PLUGIN_DIRECTORY . '/templates/images/alert.svg" class="icon-weight">';
                }
            }

            if ($countWeightSymbols === 1) {
                $htmlIconsAlert .= '<img src="' . ilLearningObjectiveSuggestionsTrackingToolPlugin::PLUGIN_DIRECTORY . '/templates/images/alert-secondary.svg" class="icon-weight">';
                $htmlIconsAlert .= '<img src="' . ilLearningObjectiveSuggestionsTrackingToolPlugin::PLUGIN_DIRECTORY . '/templates/images/alert-secondary.svg" class="icon-weight">';
            } elseif ($countWeightSymbols === 2) {
                $htmlIconsAlert .= '<img src="' . ilLearningObjectiveSuggestionsTrackingToolPlugin::PLUGIN_DIRECTORY . '/templates/images/alert-secondary.svg" class="icon-weight">';
            }

            $html .= '<div class="tracking-tool-accordion-item">';
            if ($learningObjective['suggested']) {
                $html .= '<img src="' . ilLearningObjectiveSuggestionsTrackingToolPlugin::PLUGIN_DIRECTORY . '/templates/images/tree_col.svg" class="tracking-tool-tree-icon tracking-tool-active" data-action="collapse">';
            } else {
                $html .= '<img src="' . ilLearningObjectiveSuggestionsTrackingToolPlugin::PLUGIN_DIRECTORY . '/templates/images/tree_col.svg" class="tracking-tool-tree-icon" data-action="expand">';
            }
            $html .= '<span class="learning-objective-title">';
            $html .= '<a href="' . $courseLink . '">' . $learningObjective['txt'] . '</a>';
            $html .= '</span>';

            if ($lastVisitedObjId == $learningObjectiveObjId) {
                $html .= '<span class="last-visited triangle"></span>';
            }

            $html .= '<span class="icon-check icon-check-' . $classStatusCourses . '">';
            $html .= '<img src="' . ilLearningObjectiveSuggestionsTrackingToolPlugin::PLUGIN_DIRECTORY . '/templates/images/' . $checkIcon . '">';
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

    /**
     * @param int   $refId
     * @param array $arr_usr_ids
     * @return array
     */
    public static function getData(int $refId, array $arr_usr_ids = array()): array
    {
        global $DIC;
        $ilDB = $DIC->database();
        $result = $ilDB->query(self::getSQL($refId, $arr_usr_ids));
        $locftst_data = array();


        while ($row = $ilDB->fetchAssoc($result)) {

            $locftst_state = new ilLearnObjectFinalTestState();
            $locftst_state->setLocftestUsrId($row['usr_id']);
            $locftst_state->setLocftestCrsObjId($row['learn_objective_crs_obj_id']);
            $locftst_state->setLocftestLearnObjectiveTitle($row['learn_objective_title']);
            $locftst_state->setLocftestCrsTitle($row['learn_objective_crs_title']);
            $locftst_state->setLocftestMasterObjectiveId($row['master_crs_objective_id']);
            $locftst_state->setLocftestObjectiveId($row['crs_objective_id']);
            $locftst_state->setLocftestObjectiveTitle($row['crs_objective_title']);
            $locftst_state->setLocftestTestObjId($row['tst_obj_id']);
            $locftst_state->setLocftestTestRefId($row['tst_ref_id']);
            $locftst_state->setLocftestTestTitle($row['tst_title']);
            $locftst_state->setLocftestPercentage($row['usr_percentage']);
            $locftst_state->setObjectivesAllCompleted($row['objectives_all_completed']);
            $locftst_state->setObjectivesSugCompleted($row['objectives_sug_completed']);
            $locftst_state->setObjectivesSuggested($row['suggested']);
            $locftst_state->setLocftestQplsRequiredPercentage($row['tst_req_percentage']);
            $locftst_state->setLocftestMasterCrsId($row['master_crs_id']);
            $locftst_state->setLocftestMasterCrsTitle($row['master_crs_title']);

            $locftst_data[$row['usr_id']][$row['master_crs_objective_id']][] = $locftst_state;
        }

        return $locftst_data;
    }

    /**
     * @param array $arr_usr_ids
     * @param int   $refId
     * @return string
     */
    protected static function getSQL(int $refId, array $arr_usr_ids = array()): string
    {
        global $DIC;
        $ilDB = $DIC->database();


        $learn_objectives_sugg_courses_query = new LearnObjectivesSuggCoursesQuery();
        $learn_objectives_sugg_courses_query->createTemporaryTable(LearnObjectivesSuggCoursesQuery::DEFAULT_TMP_TABLE_NAME."_1");
        $learn_objectives_sugg_courses_query->createTemporaryTable(LearnObjectivesSuggCoursesQuery::DEFAULT_TMP_TABLE_NAME."_2");
        $learn_objectives_sugg_courses_query->createTemporaryTable(LearnObjectivesSuggCoursesQuery::DEFAULT_TMP_TABLE_NAME."_3");


        $learn_objectives_courses_query = new LearnObjectivesCoursesQuery();
        $learn_objectives_final_tests_query = new LearnObjectivesFinalTestsQuery();
        $learn_objectives_final_tests_query->createTemporaryTable();

        $select = "SELECT
					learn_objective_crs.master_crs_id,
					learn_objective_crs.master_crs_title,
					learn_objective_crs.master_crs_objective_id,
					learn_objective_crs.learn_objective_title,
					learn_objective_crs.learn_objective_crs_title,
       				learn_objective_crs.learn_objective_crs_obj_id,
					final_tests.crs_objective_id,
					final_tests.crs_objective_title,
					final_tests.tst_title,
					final_tests.tst_obj_id,
					final_tests.tst_ref_id,
					final_tests.tst_req_percentage,
					crs_memb.usr_id as usr_id,
					loc_user_results.result_perc as usr_percentage,
					
    				CASE WHEN loc_user_results.result_perc >= final_tests.tst_req_percentage then 1 else 0 end as objectives_all_completed,
    				
    				
    				CASE WHEN exists (SELECT   * from ".LearnObjectivesSuggCoursesQuery::DEFAULT_TMP_TABLE_NAME."_1
 where objective_id = learn_objective_crs.master_crs_objective_id AND  user_id = crs_memb.usr_id)  AND loc_user_results.result_perc >= final_tests.tst_req_percentage then 1 else 0 end as objectives_sug_completed,
 
 
    				CASE WHEN exists (SELECT   * from ".LearnObjectivesSuggCoursesQuery::DEFAULT_TMP_TABLE_NAME."_2
 where objective_id = learn_objective_crs.master_crs_objective_id AND  user_id = crs_memb.usr_id)  then loc_user_results.result_perc else 0 end as objectives_sug_percentage,
    				
    				CASE WHEN exists (SELECT   * from ".LearnObjectivesSuggCoursesQuery::DEFAULT_TMP_TABLE_NAME."_3
 where objective_id = learn_objective_crs.master_crs_objective_id AND  user_id = crs_memb.usr_id)  then 1 else 0 end as suggested
 
 
                    FROM 
                    
                    (".$learn_objectives_courses_query->getSQL().") as learn_objective_crs
                    
                    INNER JOIN (SELECT * from ".LearnObjectivesFinalTestsQuery::DEFAULT_TMP_TABLE_NAME.") as final_tests on final_tests.crs_id = learn_objective_crs.learn_objective_crs_obj_id
                    
                    INNER JOIN obj_members as crs_memb on ".$ilDB->in('crs_memb.usr_id', $arr_usr_ids, false, 'integer')." and crs_memb.obj_id = learn_objective_crs.master_crs_id
                    
                    LEFT JOIN
    loc_user_results ON loc_user_results.course_id = final_tests.crs_id
			        AND loc_user_results.user_id = crs_memb.usr_id AND ".$ilDB->in('loc_user_results.user_id', $arr_usr_ids, false, 'integer')."
			        AND loc_user_results.type = ".ilLOUserResults::TYPE_QUALIFIED."
			        AND  loc_user_results.objective_id = final_tests.crs_objective_id 
			        WHERE learn_objective_crs.master_crs_id = " . $refId . "
			        ORDER BY learn_objective_crs.master_crs_objective_position, final_tests.crs_objective_position";

        //echo $select;	exit;
        return $select;
    }
}
