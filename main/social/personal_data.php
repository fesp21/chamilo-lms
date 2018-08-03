<?php
/* For licensing terms, see /license.txt */

use Chamilo\CoreBundle\Entity\Repository\LegalRepository;

/**
 * @package chamilo.messages
 */
$cidReset = true;

require_once __DIR__.'/../inc/global.inc.php';

api_block_anonymous_users();

if (!api_get_configuration_value('enable_gdpr')) {
    api_not_allowed(true);
}

$userId = api_get_user_id();
$userInfo = api_get_user_info();

$substitutionTerms = [
    'password' => get_lang('EncryptedData'),
    'salt' => get_lang('RandomData'),
    'empty' => get_lang('NoData'),
];

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$formToString = '';

if (api_get_setting('allow_terms_conditions') === 'true') {
    $form = new FormValidator('term', 'post', api_get_self().'?action=delete_legal&user_id='.$userId);
    $form->addHtml(Display::return_message(get_lang('WhyYouWantToDeleteYourLegalAgreement')));
    $form->addTextarea('explanation', get_lang('ExplanationDeleteLegal'), [], true);
    $form->addButtonSave(get_lang('DeleteLegal'));
    $form->addHidden('action', 'delete_legal');
    $formToString = $form->returnForm();
}

switch ($action) {
    case 'send_legal':
        $language = api_get_interface_language();
        $language = api_get_language_id($language);
        $terms = LegalManager::get_last_condition($language);
        if (!$terms) {
            //look for the default language
            $language = api_get_setting('platformLanguage');
            $language = api_get_language_id($language);
            $terms = LegalManager::get_last_condition($language);
        }

        $legalAcceptType = $terms['version'].':'.$terms['language_id'].':'.time();
        UserManager::update_extra_field_value(
            $userId,
            'legal_accept',
            $legalAcceptType
        );

        Event::addEvent(
            LOG_TERM_CONDITION_ACCEPTED,
            LOG_USER_OBJECT,
            api_get_user_info($userId),
            api_get_utc_datetime()
        );

        $bossList = UserManager::getStudentBossList($userId);
        if (!empty($bossList)) {
            $bossList = array_column($bossList, 'boss_id');
            $currentUserInfo = api_get_user_info($userId);
            foreach ($bossList as $bossId) {
                $subjectEmail = sprintf(
                    get_lang('UserXSignedTheAgreement'),
                    $currentUserInfo['complete_name']
                );
                $contentEmail = sprintf(
                    get_lang('UserXSignedTheAgreementTheY'),
                    $currentUserInfo['complete_name'],
                    api_get_local_time($time)
                );

                MessageManager::send_message_simple(
                    $bossId,
                    $subjectEmail,
                    $contentEmail,
                    $user_id
                );
            }
        }

        Display::addFlash(Display::return_message(get_lang('Saved')));
        break;
    case 'delete_legal':
        if ($form->validate()) {
            $explanation = $form->getSubmitValue('explanation');

            UserManager::create_extra_field(
                'request_for_legal_agreement_consent_removal',
                1, //text
                        'Request for legal agreement consent removal',
                ''
            );

            UserManager::update_extra_field_value(
                $userId,
                'request_for_legal_agreement_consent_removal',
                1
            );

            UserManager::create_extra_field(
                'request_for_legal_agreement_consent_removal_justification',
                1, //text
                'Request for legal agreement consent removal justification',
                ''
            );

            UserManager::update_extra_field_value(
                $userId,
                'request_for_legal_agreement_consent_removal_justification',
                $explanation
            );

            $extraFieldValue = new ExtraFieldValue('user');
            $value = $extraFieldValue->get_values_by_handler_and_field_variable(
                $userId,
                'legal_accept'
            );
            $result = $extraFieldValue->delete($value['id']);

            Display::addFlash(Display::return_message(get_lang('Deleted')));

            Event::addEvent(
                LOG_USER_REMOVED_LEGAL_ACCEPT,
                LOG_USER_OBJECT,
                $userInfo
            );

            $url = api_get_path(WEB_CODE_PATH).'admin/';
            $link = Display::url($url, $url);
            $subject = get_lang('RequestForLegalConsentRemoval');
            $content = sprintf(
                get_lang('TheUserXAskRemovalWithJustifactionYGoHere'),
                $userInfo['complete_name'],
                $explanation,
                $link
            );

            $email = api_get_configuration_value('data_protection_officer_email');
            if (!empty($email)) {
                api_mail_html('', $email, $subject, $content);
            } else {
                MessageManager::sendMessageToAllAdminUsers(api_get_user_id(), $subject, $content);
            }
        }
        break;
}

$propertiesToJson = UserManager::getRepository()->getPersonalDataToJson($userId, $substitutionTerms);

if (!empty($_GET['export'])) {
    $filename = md5(mt_rand(0, 1000000)).'.json';
    $path = api_get_path(SYS_ARCHIVE_PATH).$filename;
    $writeResult = file_put_contents($path, $propertiesToJson);
    if ($writeResult !== false) {
        DocumentManager::file_send_for_download($path, true, $filename);
        exit;
    }
}

$allowSocial = api_get_setting('allow_social_tool') === 'true';

$nameTools = get_lang('PersonalDataReport');
$show_message = null;

if ($allowSocial) {
    $this_section = SECTION_SOCIAL;
    $interbreadcrumb[] = [
        'url' => api_get_path(WEB_PATH).'main/social/home.php',
        'name' => get_lang('SocialNetwork'),
    ];
} else {
    $this_section = SECTION_MYPROFILE;
    $interbreadcrumb[] = [
        'url' => api_get_path(WEB_PATH).'main/auth/profile.php',
        'name' => get_lang('Profile'),
    ];
}

$interbreadcrumb[] = ['url' => '#', 'name' => get_lang('PersonalDataReport')];

// LEFT CONTENT
$socialMenuBlock = '';
if ($allowSocial) {
    // Block Social Menu
    $socialMenuBlock = SocialManager::show_social_menu('personal-data');
}

// MAIN CONTENT
$personalDataContent = '<ul>';
$properties = json_decode($propertiesToJson);

foreach ($properties as $key => $value) {
    if (is_array($value) || is_object($value)) {
        switch ($key) {
            case 'extraFields':
                $personalDataContent .= '<li>'.$key.': </li><ul>';
                foreach ($value as $subValue) {
                    $personalDataContent .= '<li>'.$subValue->variable.': '.$subValue->value.'</li>';
                }
                $personalDataContent .= '</ul>';
                break;
            case 'portals':
            case 'achievedSkills':
            case 'sessionAsGeneralCoach':
            case 'classes':
            case 'courses':
                $personalDataContent .= '<li>'.$key.': </li><ul>';
                foreach ($value as $subValue) {
                    $personalDataContent .= '<li>'.$subValue.'</li>';
                }
                $personalDataContent .= '</ul>';
                break;
            case 'sessionCourseSubscriptions':
                $personalDataContent .= '<li>'.$key.': </li><ul>';
                foreach ($value as $session => $courseList) {
                    $personalDataContent .= '<li>'.$session.'<ul>';
                    foreach ($courseList as $course) {
                        $personalDataContent .= '<li>'.$course.'</li>';
                    }
                    $personalDataContent .= '</ul>';
                }
                $personalDataContent .= '</ul>';
                break;
        }

        /*foreach ($value as $subValue) {
            foreach ($subValue as $subSubValue) {
                var_dump($subSubValue);
                //$personalDataContent .= '<li>'.$subSubValue.'</li>';
            }
        }*/
        //skip in some cases
        /*sif (!empty($value['date'])) {
            $personalDataContent .= '<li>'.$key.': '.$value['date'].'</li>';
        } else {
            $personalDataContent .= '<li>'.$key.': '.get_lang('ComplexDataNotShown').'</li>';
        }*/
    } else {
        $personalDataContent .= '<li>'.$key.': '.$value.'</li>';
    }
}
$personalDataContent .= '</ul>';

// Check terms acceptation
$permitionBlock = '';
if (api_get_setting('allow_terms_conditions') === 'true') {
    $extraFieldValue = new ExtraFieldValue('user');
    $value = $extraFieldValue->get_values_by_handler_and_field_variable(
        $userId,
        'legal_accept'
    );
    $permitionBlock .= Display::return_icon('accept_na.png', get_lang('NotAccepted'));
    if (isset($value['value']) && !empty($value['value'])) {
        list($legalId, $legalLanguageId, $legalTime) = explode(':', $value['value']);
        $permitionBlock = get_lang('CurrentStatus').': '.
            Display::return_icon('accept.png', get_lang('LegalAgreementAccepted')).get_lang('LegalAgreementAccepted').
            '<br />';
        $permitionBlock .= get_lang('Date').': '.api_get_local_time($legalTime).'<br />';
        $permitionBlock .= $formToString;

    /*$permitionBlock .= Display::url(
        get_lang('DeleteLegal'),
        api_get_self().'?action=delete_legal&user_id='.$userId,
        ['class' => 'btn btn-danger btn-xs']
    );*/
    } else {
        // @TODO add action handling for button
        $permitionBlock .= Display::url(
            get_lang('SendLegal'),
            api_get_self().'?action=send_legal&user_id='.$userId,
            ['class' => 'btn btn-primary btn-xs']
        );
    }
} else {
    $permitionBlock .= get_lang('NoTermsAndConditionsAvailable');
}

//Build the final array to pass to template
$personalData = [];
$personalData['data'] = $personalDataContent;
//$personalData['responsible'] = api_get_setting('personal_data_responsible_org');

$em = Database::getManager();
/** @var LegalRepository $legalTermsRepo */
$legalTermsRepo = $em->getRepository('ChamiloCoreBundle:Legal');
// Get data about the treatment of data
$treatmentTypes = LegalManager::getTreatmentTypeList();

foreach ($treatmentTypes as $id => $item) {
    $personalData['treatment'][$item]['title'] = get_lang('PersonalData'.ucfirst($item).'Title');
    $legalTerm = $legalTermsRepo->findOneByTypeAndLanguage($id, api_get_language_id($user_language));
    $legalTermContent = '';
    if (!empty($legalTerm[0]) && is_array($legalTerm[0])) {
        $legalTermContent = $legalTerm[0]['content'];
    }
    $personalData['treatment'][$item]['content'] = $legalTermContent;
}
$officerName = api_get_configuration_value('data_protection_officer_name');
$officerRole = api_get_configuration_value('data_protection_officer_role');
$officerEmail = api_get_configuration_value('data_protection_officer_email');
if (!empty($officerName)) {
    $personalData['officer_name'] = $officerName;
    $personalData['officer_role'] = $officerRole;
    $personalData['officer_email'] = $officerEmail;
}

$tpl = new Template(null);

$actions = Display::url(
    Display::return_icon('excel.png', get_lang('Export'), [], ICON_SIZE_MEDIUM),
    api_get_path(WEB_CODE_PATH).'social/personal_data.php?export=1'
);

$tpl->assign('actions', Display::toolbarAction('toolbar', [$actions]));

$termLink = '';
if (api_get_setting('allow_terms_conditions') === 'true') {
    $url = api_get_path(WEB_CODE_PATH).'social/terms.php';
    $termLink = Display::url($url, $url);
}

// Block Social Avatar
SocialManager::setSocialUserBlock($tpl, api_get_user_id(), 'messages');
if (api_get_setting('allow_social_tool') === 'true') {
    $tpl->assign('social_menu_block', $socialMenuBlock);
    $tpl->assign('personal_data', $personalData);
} else {
    $tpl->assign('social_menu_block', '');
    $tpl->assign('personal_data_block', $personalDataContent);
}

$tpl->assign('permission', $permitionBlock);
$tpl->assign('term_link', $termLink);
$socialLayout = $tpl->get_template('social/personal_data.tpl');
$tpl->display($socialLayout);
