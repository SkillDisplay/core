<?php

declare(strict_types=1);

defined('TYPO3') || die('Access denied.');

use Psr\Log\LogLevel;
use SkillDisplay\Skills\Controller\CampaignController;
use SkillDisplay\Skills\Controller\CertificationController;
use SkillDisplay\Skills\Controller\NotificationController;
use SkillDisplay\Skills\Controller\OrganisationController;
use SkillDisplay\Skills\Controller\PaymentController;
use SkillDisplay\Skills\Controller\PortalController;
use SkillDisplay\Skills\Controller\SearchController;
use SkillDisplay\Skills\Controller\ShortLinkController;
use SkillDisplay\Skills\Controller\SkillController;
use SkillDisplay\Skills\Controller\SkillPathController;
use SkillDisplay\Skills\Controller\UserController;
use SkillDisplay\Skills\Controller\VerificationCreditController;
use SkillDisplay\Skills\Controller\VerifierController;
use SkillDisplay\Skills\Domain\Model\FileReference;
use SkillDisplay\Skills\Domain\Model\Notification;
use SkillDisplay\Skills\Domain\Repository\SkillGroupRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Handler\VerificationNotificationHandler;
use SkillDisplay\Skills\Hook\DataHandlerHook;
use SkillDisplay\Skills\Routing\RestApiEnhancer;
use SkillDisplay\Skills\Routing\UidMapper;
use SkillDisplay\Skills\Service\NotificationService;
use SkillDisplay\Skills\Service\ShortLinkService;
use SkillDisplay\Skills\TypeConverter\ObjectStorageConverter;
use SkillDisplay\Skills\TypeConverter\UploadedFileReferenceConverter;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Log\Writer\FileWriter;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\Container\Container;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionUtility::registerTypeConverter(UploadedFileReferenceConverter::class);
ExtensionUtility::registerTypeConverter(ObjectStorageConverter::class);

// these controller/action combinations must be allowed also in the ShortLink plugin below!
ShortLinkService::addHandler('userConfirm', ['User', 'confirm']);
ShortLinkService::addHandler('emailConfirm', ['User', 'confirmEmail']);

$notificationHandler = GeneralUtility::makeInstance(VerificationNotificationHandler::class);
NotificationService::registerHandler(Notification::TYPE_VERIFICATION_GRANTED, $notificationHandler);
NotificationService::registerHandler(Notification::TYPE_VERIFICATION_REJECTED, $notificationHandler);
NotificationService::registerHandler(Notification::TYPE_VERIFICATION_REVOKED, $notificationHandler);
NotificationService::registerHandler(Notification::TYPE_VERIFICATION_REQUESTED, $notificationHandler);

$GLOBALS['EXTCONF']['skills']['SkillGroups']['skillPath'] = SkillPathRepository::class;
$GLOBALS['EXTCONF']['skills']['SkillGroups']['skillGroup'] = SkillGroupRepository::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = DataHandlerHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = DataHandlerHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearPageCacheEval']['skills'] = DataHandlerHook::class . '->clearProgressCache';

$GLOBALS['TYPO3_CONF_VARS']['SYS']['routing']['enhancers']['RestApi'] = RestApiEnhancer::class;
$GLOBALS['TYPO3_CONF_VARS']['SYS']['routing']['aspects']['UidMapper'] = UidMapper::class;

GeneralUtility::makeInstance(Container::class)
    ->registerImplementation(\TYPO3\CMS\Extbase\Domain\Model\FileReference::class, FileReference::class);

ExtensionManagementUtility::addTypoScriptSetup(trim(
    '
    config.pageTitleProviders {
        skills {
            provider = SkillDisplay\Skills\Seo\PageTitleProvider
            before = altPageTitle,record,seo
        }
    }'
));

ExtensionUtility::configurePlugin(
    'Skills',
    'Skills',
    [
        SkillPathController::class => 'listByBrand, show',
        SkillController::class => 'show',
    ],
    // non-cacheable actions
    [
        SkillPathController::class => 'listByBrand',
    ]
);

ExtensionUtility::configurePlugin(
    'Skills',
    'Users',
    [
        UserController::class => 'new, create, confirm, confirmEmail, success, terms, acceptTerms',
    ],
    // non-cacheable actions
    [
        UserController::class => 'new, create, confirm, confirmEmail, terms, acceptTerms',
    ]
);

ExtensionUtility::configurePlugin(
    'Skills',
    'ShortLink',
    [
        ShortLinkController::class => 'handle',
        UserController::class => 'confirm,confirmEmail',
    ],
    // non-cacheable actions
    [
        ShortLinkController::class => 'handle',
        UserController::class => 'confirm,confirmEmail',
    ]
);

ExtensionUtility::configurePlugin(
    'Skills',
    'Routing',
    [
        UserController::class => 'route',
    ],
    // non-cacheable actions
    [
        UserController::class => 'route',
    ]
);

ExtensionUtility::configurePlugin(
    'Skills',
    'Organisations',
    [
        OrganisationController::class => 'list,show',
    ]
);

$apiActions = [
    CertificationController::class => 'recent,recentRequests,modify,userCancel,listForVerifier,history,show,create,listForOrganisation',
    SkillPathController::class => 'list,showApi,certificateDownload,syllabusForSetPdf,completeDownloadForSetPdf,progressForSet,getAwardsForSkillSet',
    SkillController::class => 'show,skillUpAjax',
    VerifierController::class => 'show,forSkill,listOfUser',
    UserController::class => 'starCertifierAjax,show,countries,baseData,updatePassword,patrons,updateNotifications,updateEmail,updateSocialPlatforms,updateProfile,publicProfile,downloadPublicProfilePdf,publicProfileVerifications,getOrganizationsForCurrentUser,getAllAwards,updateAwardSelection',
    OrganisationController::class => 'leave,joinOrganisation,removeMember,createInvitationCodesAjax,organisationStatistics,downloadCsvStatistics,show,setAccountOverdraw,managerList,getBillingInformation,verificationList',
    SearchController::class => 'search',
    CampaignController::class => 'getForUser',
    PortalController::class => 'links',
    VerificationCreditController::class => 'overview,list,add',
    PaymentController::class => 'getSubscription, getCustomerPortalUrl',
    NotificationController::class => 'show, deleteNotifications',
];

ExtensionUtility::configurePlugin(
    'Skills',
    'Api',
    $apiActions,
    // non-cacheable actions
    $apiActions
);

ExtensionUtility::configurePlugin(
    'Skills',
    'Anonymous',
    [
        UserController::class => 'anonymousRequest, anonymousCreate',
    ],
    // non-cacheable actions
    [
        UserController::class => 'anonymousRequest, anonymousCreate',
    ]
);

$GLOBALS['TYPO3_CONF_VARS']['LOG']['SkillDisplay']['Skills']['writerConfiguration'] = [
    LogLevel::INFO => [
        FileWriter::class => [
            'logFileInfix' => 'skills',
        ],
    ],
];

$caches = [
    'skill_progress',
    'skillset_progress',
];
foreach ($caches as $cacheName) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$cacheName] = [
        'frontend' => VariableFrontend::class,
        'backend' => Typo3DatabaseBackend::class,
        'options' => [
            'defaultLifetime' => 2592000,
        ],
        'groups' => ['lowlevel'],
    ];
}
