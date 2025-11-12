<?php

use ILIAS\UI\Component\Input\Container\Form\Standard;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Config\CourseConfig;
use Mpdf\MpdfException;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\PdfParserException;
use setasign\Fpdi\PdfParser\Type\PdfTypeException;

/**
 * @ilCtrl_isCalledBy ilLearningObjectiveSuggestionsTrackingToolPluginGUI: ilPCPluggedGUI
 * @ilCtrl_isCalledBy ilLearningObjectiveSuggestionsTrackingToolPluginGUI: ilUIPluginRouterGUI
 * /
 */
class ilLearningObjectiveSuggestionsTrackingToolPluginGUI extends ilPageComponentPluginGUI
{
    const CMD_PRINT_CERTIFICATE = 'printCertificate';

    private $tpl;

    private \ILIAS\DI\Container $dic;

    private ilCtrlInterface $ctrl;

    private ilPlugin $pl;

    private \ILIAS\UI\Factory $factory;

    private \ILIAS\UI\Renderer $renderer;

    private ?int $refId = null;

    private ?int $userId = null;


    public function __construct()
    {
        global $DIC;

        parent::__construct();
        $this->dic = $DIC;
        $this->ctrl = $this->dic->ctrl();
        $this->pl = ilLearningObjectiveSuggestionsTrackingToolPlugin::getInstance();
        $this->lng = $this->dic->language();
        $this->factory = $this->dic->ui()->factory();
        $this->renderer = $this->dic->ui()->renderer();
    }

    /**
     * Commands
     *
     * @return void
     * @throws ilCtrlException
     */
    public function executeCommand(): void
    {
        $cmd = $this->ctrl->getCmd();

        $commands = [
            'create',
            'update',
            'edit',
            'cancel',
            'printCertificate'
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

        $this->pluginTemplate();

        $userId = $DIC->user()->getId();
        $learningObjectives = $this->getTrackingToolLearningObjectives((string) $userId, $a_properties['ref_id'] ?? null);

        // TODO
        $entryTest = [];
        if (!empty($a_properties['ref_id'])) {
            $courseObjId = ilObjCourse::_lookupObjectId($a_properties['ref_id']);
            $entryTest = $this->getDataEntryTest($courseObjId);
        }

        $this->buildAccordionHtml($learningObjectives, $userId, $a_properties['ref_id'] ?? null);

        return $this->tpl->get();
    }

    /**
     * @throws ilCtrlException
     */
    private function getModal()
    {
        $modalFormAction = $this->ctrl->getLinkTargetByClass(
            [ilUIPluginRouterGUI::class, ilLearningObjectiveSuggestionsTrackingToolPluginGUI::class],
            'printCertificate'
        );

        $participationCertificatePlugin = ilParticipationCertificatePlugin::getInstance();

        $userId = $this->dic->user()->getId();

        $userData = ilPartCertUsersData::getData($participationCertificatePlugin, [$userId]);
        $firstname = $userData[$userId]->getPartCertFirstname();
        $lastname = $userData[$userId]->getPartCertLastname();

        $firstnameField = $this->factory->input()->field()->text($this->pl->txt('firstname'))
                                        ->withDedicatedName('firstname')
                                        ->withValue($firstname ?? '')
                                        ->withDisabled(!empty($firstname));

        $lastnameField = $this->factory->input()->field()->text($this->pl->txt('lastname'))
                                       ->withDedicatedName('lastname')
                                       ->withValue($lastname ?? '')
                                       ->withDisabled(!empty($lastname));

        $hiddenFirstnameField = $this->factory->input()->field()->hidden()
                                       ->withDedicatedName('hidden_firstname')
                                       ->withValue($firstname ?? '');

        $hiddenLastnameField = $this->factory->input()->field()->hidden()
                                              ->withDedicatedName('hidden_lastname')
                                              ->withValue($firstname ?? '');

        $sectionUserData = $this->factory->input()->field()->section(
            [
                'firstname' => $firstnameField,
                'lastname' => $lastnameField,
                'hidden_firstname' => $hiddenFirstnameField,
                'hidden_lastname' => $hiddenLastnameField
            ],
            '',
            ''
        )->withDedicatedName('user_data');

        $userFields['user_data'] = $sectionUserData;

        $sectionInfo = $this->factory->input()->field()->section(
            [],
            '',
            $this->pl->txt('modal_box_info')
        );

        $info['info'] = $sectionInfo;

        $courses = $this->getUserCourses($userId);

        $checkboxes = [];
        foreach ($courses as $course) {
            $checkboxes['course_' . $course['obj_id']] = $this->factory->input()->field()->checkbox(
                ilObjCourse::_lookupTitle($course['obj_id'])
            )->withDedicatedName('course_' . $course['obj_id']);

            $checkboxes['course_suggested_courses_' . $course['obj_id']] = $this->factory->input()->field()->checkbox(
                $this->pl->txt('suggested_courses')
            )->withDedicatedName('course_suggested_courses_' . $course['obj_id']);

            $checkboxes['course_personalized_additional_offer_' . $course['obj_id'] ] = $this->factory->input()->field()->checkbox(
                $this->pl->txt('personalized_additional_offer')
            )->withDedicatedName('course_personalized_additional_offer_'  . $course['obj_id']);

            $checkboxes['course_entry_test_' . $course['obj_id']] = $this->factory->input()->field()->checkbox(
                $this->pl->txt('entry_test')
            )->withDedicatedName('course_entry_test_' . $course['obj_id']);
        }

        $sectionCheckboxes = $this->factory->input()->field()->section(
            $checkboxes,
            '',
            ''
        )->withDedicatedName('options');

        $optionsFields['options'] = $sectionCheckboxes;


        $fields = array_merge($userFields, $info, $optionsFields);

        $eMentoring = (bool) ilParticipationCertificateConfig::getConfig('enable_ementoring', $this->refId);

        if ($eMentoring) {
            $sectionEMentoring = $this->factory->input()->field()->section(
                [
                    $this->factory->input()->field()->checkbox(
                        $this->pl->txt('ementoring')
                    )->withDedicatedName('status')
                ],
                '',
                ''
            )->withDedicatedName('ementoring');

            $eMentoringField['ementoring'] = $sectionEMentoring;
            $fields = array_merge($fields, $eMentoringField);
        }

        $modal = $this->factory->modal()->roundtrip(
            $this->pl->txt('modal_title_1'),
            [
                $this->factory->messageBox()->info('Something.')
            ],
            $fields,
            $modalFormAction
        )->withDedicatedName('tracking-tool-modal')
         ->withSubmitLabel($this->pl->txt('modal_box_submit_button'))
         ->withOnLoadCode(function ($id) {
                return <<<JS
        
                const form = $('.modal-footer form');
                const submitButton = form.find('button:not(.close)');

                const firstname = $('input[name="form/user_data/firstname"]');
                const lastname = $('input[name="form/user_data/lastname"]');
                const entryTest = $('input[name="form/user_data/entry_test"]');
                
                
                /*function toggleButton() {
                   alert(firstname.val());
                   alert(lastname.val());
                   
                    if (firstname.val().length === 0 || lastname.val().length === 0) {
                        submitButton.prop('disabled', true);
                    } else {
                        submitButton.prop('disabled', false);
                    }
                }

                toggleButton(); // initial check
                 
                firstname.change(function() {
                   toggleButton()
                });
                 
                lastname.change(function() {
                   toggleButton()
                });*/
        JS;
            });
        ;

        return $modal->withOnLoad($modal->getShowSignal());
    }

    /**
     * @param string      $userId
     * @param string|null $refId
     * @return array
     */
    private function getTrackingToolLearningObjectives(string $userId, ?string $refId = null): array
    {
        $sorted = $this->sortByScore($userId);

        $learningObjectives = [];
        if (!empty($refId)) {

            $courseObjId = ilObjCourse::_lookupObjectId($refId);
            $finalTestsStates = self::getData($courseObjId, [$userId]);

            $requiredPercentages = [];
            foreach ($finalTestsStates as $key => $finalTestState) {
                foreach ($finalTestState as $k => $value) {
                    $masterCrsId = $value[0]->getLocftestMasterCrsId();
                    $dataFinalTest = $this->getDataFinalTest($courseObjId);

                    $tst = null;
                    if (!empty($dataFinalTest['qtest'])) {
                        $tst = new ilObjTest($dataFinalTest['qtest'], true);
                    }

                    if ($tst instanceof ilObjTest) {
                        $schema = $tst->getMarkSchema();
                        foreach ($schema->getMarkSteps() as $mark) {
                            if ($mark->getPassed()) {
                                $requiredPercentages[$masterCrsId] = (int) $mark->getMinimumLevel();
                                break;
                            }
                        }
                    }

                    if (empty($requiredPercentages)) {
                        $requiredPercentages[$masterCrsId] = 60;
                    }
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
        return $learningObjectives;
    }

    /**
     * @return void
     * @throws ilSystemStyleException
     * @throws ilTemplateException
     */
    private function pluginTemplate(): void
    {
        $template = $this->dic->ui()->mainTemplate();
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
     * @param array       $learningObjectives
     * @param int         $userId
     * @param string|null $propertiesRefId
     * @return void
     * @throws ilCtrlException
     */
    private function buildAccordionHtml(
        array $learningObjectives,
        int $userId,
        ?string $propertiesRefId = null
    ): void {
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

            $courseRefId = $this->getCourseRefId($learningObjectiveObjId);
            $this->setRefIdAsClassParameter($courseRefId);
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
            $html .= $this->buildAccordionDropdownHtml(
                $learningObjective['courses'],
                $learningObjective['required_percentage'],
            );

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

        $this->refId = $this->fetchUrlParameter('tracking_tool_ref_id', FILTER_SANITIZE_NUMBER_INT);

        if (!empty($this->refId)) {


            $this->ctrl->setParameterByClass(
                self::class,
                'tracking_tool_ref_id',
                $this->refId
            );

            $html .= $this->renderer->render(
                component: [$this->getModal()]
            );
        }
        $this->setTemplateBlock($html, $propertiesRefId);
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
     * @param string      $html
     * @param string|null $refId
     * @return void
     * @throws ilCtrlException
     */
    private function setTemplateBlock(
        string $html,
        ?string $refId = null
    ): void {
        $this->tpl->setCurrentBlock('tracking_tool');
        $this->setVariables($html, $refId);
        $this->tpl->parseCurrentBlock();
    }

    /**
     * @param string      $html
     * @param string|null $refId
     * @return void
     * @throws ilCtrlException
     * @throws Exception
     */
    private function setVariables(
        string $html,
        ?string $refId = null
    ): void {
        $this->tpl->setVariable('TITLE', $this->pl->txt('title'));
        $this->tpl->setVariable('SUBTITLE', $this->pl->txt('subtitle'));
        $this->tpl->setVariable('DESCRIPTION', $this->pl->txt('description'));
        $this->tpl->setVariable('LEARNING_SUGGESTION', $this->pl->txt('learning_suggestion') . ' <span class="lets-get-started">' . $this->pl->txt('lets_get_started') . '</span>');
        $this->tpl->setVariable('HTML', $html);

        if(!empty($refId)) {
            $certificateAccess = new ilParticipationCertificateAccess($refId);
            $printButtonCssClass = 'print-button';
            if($certificateAccess->isSelfPrintEnabled()) {
                $printButtonCssClass .= '-visible';
            } else {
                $printButtonCssClass .= '-hidden';
            }

            $this->tpl->setVariable('PRINT_BUTTON_CLASS', $printButtonCssClass);
            $printLink = $this->buildPrintLink($refId);
            $this->tpl->setVariable('PRINT_BUTTON_LINK', $printLink);
        }

        $this->tpl->setVariable('SUGGESTED_COURSES_TITLE', $this->pl->txt('suggested_courses_title'));
        $this->tpl->setVariable('NOT_SUGGESTED_COURSES_TITLE', $this->pl->txt('not_suggested_courses_title'));


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
     * @param string $refId
     * @return string
     * @throws ilCtrlException
     */
    private function buildPrintLink(string $refId): string
    {
        $itemRefId = $this->fetchUrlParameter('item_ref_id', FILTER_DEFAULT);

        $this->ctrl->setParameterByClass(
            'ilObjCategoryGUI',
            'item_ref_id',
            $itemRefId
        );

        $this->ctrl->setParameterByClass(
            'ilObjCategoryGUI',
            'tracking_tool_ref_id',
            $refId
        );

        return $this->ctrl->getLinkTargetByClass(
            [ilRepositoryGUI::class, ilObjCategoryGUI::class],
            'view'
        );
    }

    /**
     * @return void
     * @throws MpdfException
     * @throws LoaderError
     * @throws SyntaxError
     * @throws CrossReferenceException
     * @throws PdfParserException
     * @throws PdfTypeException
     * @throws arException
     * @throws ilCtrlException
     * @throws ilDateTimeException
     */
    private function printCertificate(): void
    {
        $refinery = $this->dic->refinery();
        $http = $this->dic->http()->wrapper()->query();

        $request = $this->dic->http()->wrapper()->post();
        $eMentoring = false;


        if ($request->has('form/user_data/firstname')) {
            $firstname = $request->retrieve(
                'form/user_data/firstname',
                $refinery->kindlyTo()->string()
            );

            if (empty($firstname)) {
                /*$this->ctrl->setParameterByClass(
                    'ilObjCategoryGUI',
                    'ref_id',
                    $this->fetchUrlParameter('ref_id', FILTER_DEFAULT)
                );*/

                dd("NOT VALID DATA");
                // TODO check redirection
                $this->ctrl->redirectByClass(
                    [ilRepositoryGUI::class, ilObjCategoryGUI::class],
                    'view'
                );
            }
        } elseif ($request->has('form/user_data/hidden_firstname')) {
            $firstname = $request->retrieve(
                'form/user_data/hidden_firstname',
                $refinery->kindlyTo()->string()
            );
        }

        if ($request->has('form/user_data/lastname')) {
            $lastname = $request->retrieve(
                'form/user_data/lastname',
                $refinery->kindlyTo()->string()
            );

            if (empty($lastname)) {
                dd("NOT VALID DATA");
                // TODO check redirection
                $this->ctrl->redirectByClass(
                    [ilRepositoryGUI::class, ilObjCategoryGUI::class],
                    'view'
                );
            }
        } elseif ($request->has('form/user_data/hidden_lastname')) {
            $lastname = $request->retrieve(
                'form/user_data/hidden_lastname',
                $refinery->kindlyTo()->string()
            );
        }

        $userId = $this->dic->user()->getId();
        $courses = $this->getUserCourses($userId);

        $coursesToPrint = [];
        foreach ($courses as $course) {

            if ($request->has('form/options/course_' . $course['obj_id'])) {
                $courseObjId = $request->retrieve(
                    'form/options/course_' . $course['obj_id'],
                    $refinery->kindlyTo()->string()
                );

                $coursesToPrint[$course['obj_id']] = [];
            }

            if ($request->has('form/options/course_suggested_courses_' . $course['obj_id'])) {
                $suggestedCourses = $request->retrieve(
                    'form/options/course_suggested_courses_' . $course['obj_id'],
                    $refinery->kindlyTo()->string()
                );

                $coursesToPrint[$course['obj_id']]['suggested_courses'] = true;
            } else {
                $coursesToPrint[$course['obj_id']]['suggested_courses'] = false;
            }

            if ($request->has('form/options/course_personalized_additional_offer_' . $course['obj_id'])) {
                $additionalOffer = $request->retrieve(
                    'form/options/course_personalized_additional_offer_' . $course['obj_id'],
                    $refinery->kindlyTo()->string()
                );
                $coursesToPrint[$course['obj_id']]['additional_offer'] = true;
            } else {
                $coursesToPrint[$course['obj_id']]['additional_offer'] = false;
            }

            if ($request->has('form/options/course_entry_test_' . $course['obj_id'])) {
                $entryTest = $request->retrieve(
                    'form/options/course_entry_test_' . $course['obj_id'],
                    $refinery->kindlyTo()->string()
                );
                $coursesToPrint[$course['obj_id']]['entry_test'] = true;
            } else {
                $coursesToPrint[$course['obj_id']]['entry_test'] = false;
            }
        }

        if ($request->has('form/ementoring/status')) {
            $eMentoringStatus = $request->retrieve(
                'form/ementoring/status',
                $refinery->kindlyTo()->string()
            );


            if ($eMentoringStatus === 'checked') {
                $eMentoring = true;
            }
        }


        foreach ($coursesToPrint as $objId => $course) {
            $objCourseRefId = $this->getCourseRefId($objId);

            $printError = false;
            $certificateAccess = new ilParticipationCertificateAccess($objCourseRefId);

            if ($certificateAccess->hasCurrentUserPrintAccess()) {

                //$ementoring = (bool) ilParticipationCertificateConfig::getConfig('enable_ementoring', $this->refId);

                $userId = $this->dic->user()->getId();

                $participationCertificatePlugin = ilParticipationCertificatePlugin::getInstance();

                $userData = ilPartCertUsersData::getData($participationCertificatePlugin, [$userId]);

                $twigParser = new ilParticipationCertificateTwigParser(
                    $objCourseRefId,
                    [],
                    [$userId],
                    $eMentoring,
                    false
                );

                $twigParser->parseData($objCourseRefId);
            } else {
                $printError = true;
                continue;
                // TODO test it

            }
        }

        /*if ($printError) {
            $this->tpl->setOnScreenMessage('failure',$this->lng->txt('no_permission'), true);
            $this->dic->ctrl()->redirectToURL('login.php');
        }*/


        /*$this->refId = $http->has('tracking_tool_ref_id') ? $http->retrieve(
            'tracking_tool_ref_id',
            $refinery->kindlyTo()->string()
        ) : null;

        $certificateAccess = new ilParticipationCertificateAccess($this->refId);

        if ($certificateAccess->hasCurrentUserPrintAccess()) {

            //$ementoring = (bool) ilParticipationCertificateConfig::getConfig('enable_ementoring', $this->refId);

            $userId = $this->dic->user()->getId();

            $participationCertificatePlugin = ilParticipationCertificatePlugin::getInstance();

            $userData = ilPartCertUsersData::getData($participationCertificatePlugin, [$userId]);

            $twigParser = new ilParticipationCertificateTwigParser(
                $this->refId,
                [],
                [$userId],
                $eMentoring,
                false
            );

            $twigParser->parseData($this->refId);
        } else {

            // TODO test it
            $this->tpl->setOnScreenMessage('failure',$this->lng->txt('no_permission'), true);
            $this->dic->ctrl()->redirectToURL('login.php');
        }*/
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

    /**
     * @param int $objId
     * @return array
     */
    public static function getDataFinalTest(int $objId): array
    {
        global $DIC;
        $ilDB = $DIC->database();

        $result = $ilDB->queryF(
            "SELECT * FROM loc_settings
              WHERE obj_id = %s AND qtest IS NOT NULL",
            ['integer'],
            [$objId]
        );

        $data = [];
        while ($row = $ilDB->fetchAssoc($result)) {
            $data = $row;
        }

        return $data;
    }

    /**
     * @param int $objId
     * @return array
     */
    public static function getDataEntryTest(int $objId): array
    {
        global $DIC;
        $ilDB = $DIC->database();

        $result = $ilDB->queryF(
            "SELECT * FROM loc_settings
              WHERE obj_id = %s AND itest IS NOT NULL",
            ['integer'],
            [$objId]
        );

        $data = [];
        while ($row = $ilDB->fetchAssoc($result)) {
            $data = $row;
        }

        return $data;
    }

    /**
     * @param int $userId
     * @return array
     */
    public static function getUserCourses(int $userId): array
    {
        global $DIC;
        $ilDB = $DIC->database();

        $courses = CourseConfig::get();
        $courseObjIds = array_map(function($config) {
            return $config->getCourseObjId();
        }, $courses);

        $uniqueCourseObjIds = array_values(array_unique($courseObjIds));

        $in = $ilDB->in('obj_id', $uniqueCourseObjIds, false, 'integer');

        $result = $ilDB->queryF(
            "SELECT * FROM obj_members
              WHERE usr_id = %s AND $in AND member = 1",
            ['integer'],
            [$userId]
        );

        $data = [];
        while ($row = $ilDB->fetchAssoc($result)) {
            $data[] = $row;
        }

        return $data;
    }

    /**
     * @param string $param
     * @param int    $filter
     * @return int|null
     */
    protected function fetchUrlParameter(string $param, int $filter): ?int
    {
        return filter_input(INPUT_GET, $param, $filter);
    }

}
