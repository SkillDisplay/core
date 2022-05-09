<?php
defined('TYPO3_MODE') || die('Access denied.');

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerTypeConverter(SkillDisplay\Skills\TypeConverter\UploadedFileReferenceConverter::class);
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerTypeConverter(SkillDisplay\Skills\TypeConverter\ObjectStorageConverter::class);

// these controller/action combinations must be allowed also in the ShortLink plugin below!
\SkillDisplay\Skills\Service\ShortLinkService::addHandler('userConfirm', ['User', 'confirm']);
\SkillDisplay\Skills\Service\ShortLinkService::addHandler('emailConfirm', ['User', 'confirmEmail']);

\SkillDisplay\Skills\Service\NotificationService::registerHandler(\SkillDisplay\Skills\Domain\Model\Notification::TYPE_VERIFICATION_GRANTED, \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\SkillDisplay\Skills\Handler\VerificationNotificationHandler::class));
\SkillDisplay\Skills\Service\NotificationService::registerHandler(\SkillDisplay\Skills\Domain\Model\Notification::TYPE_VERIFICATION_REJECTED, \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\SkillDisplay\Skills\Handler\VerificationNotificationHandler::class));
\SkillDisplay\Skills\Service\NotificationService::registerHandler(\SkillDisplay\Skills\Domain\Model\Notification::TYPE_VERIFICATION_REVOKED, \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\SkillDisplay\Skills\Handler\VerificationNotificationHandler::class));
\SkillDisplay\Skills\Service\NotificationService::registerHandler(\SkillDisplay\Skills\Domain\Model\Notification::TYPE_VERIFICATION_REQUESTED, \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\SkillDisplay\Skills\Handler\VerificationNotificationHandler::class));

$GLOBALS['EXTCONF']['skills']['SkillGroups']['skillPath'] = \SkillDisplay\Skills\Domain\Repository\SkillPathRepository::class;
$GLOBALS['EXTCONF']['skills']['SkillGroups']['skillGroup'] = \SkillDisplay\Skills\Domain\Repository\SkillGroupRepository::class;


$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = \SkillDisplay\Skills\Hook\DataHandlerHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = \SkillDisplay\Skills\Hook\DataHandlerHook::class;

$GLOBALS['TYPO3_CONF_VARS']['SYS']['routing']['enhancers']['RestApi'] = \SkillDisplay\Skills\Routing\RestApiEnhancer::class;
$GLOBALS['TYPO3_CONF_VARS']['SYS']['routing']['aspects']['UidMapper'] = \SkillDisplay\Skills\Routing\UidMapper::class;

\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\Container\Container::class)
    ->registerImplementation(\TYPO3\CMS\Extbase\Domain\Model\FileReference::class, \SkillDisplay\Skills\Domain\Model\FileReference::class);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptSetup(trim('
    config.pageTitleProviders {
        skills {
            provider = SkillDisplay\Skills\Seo\PageTitleProvider
            before = altPageTitle,record,seo
        }
    }'
));

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'Skills',
    'Skills',
    [
        \SkillDisplay\Skills\Controller\SkillPathController::class => 'listByBrand, show',
        \SkillDisplay\Skills\Controller\SkillController::class => 'show',
    ],
    // non-cacheable actions
    [
    ]
);

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'Skills',
    'Users',
    [
        \SkillDisplay\Skills\Controller\UserController::class => 'new, create, confirm, confirmEmail, success, terms, acceptTerms'
    ],
    // non-cacheable actions
    [
        \SkillDisplay\Skills\Controller\UserController::class => 'new, create, confirm, confirmEmail, terms, acceptTerms'
    ]
);

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'Skills',
    'ShortLink',
    [
        \SkillDisplay\Skills\Controller\ShortLinkController::class => 'handle',
        \SkillDisplay\Skills\Controller\UserController::class => 'confirm,confirmEmail',
    ],
    // non-cacheable actions
    [
        \SkillDisplay\Skills\Controller\ShortLinkController::class => 'handle',
        \SkillDisplay\Skills\Controller\UserController::class => 'confirm,confirmEmail',
    ]
);

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'Skills',
    'Routing',
    [
        \SkillDisplay\Skills\Controller\UserController::class => 'route',
    ],
    // non-cacheable actions
    [
        \SkillDisplay\Skills\Controller\UserController::class => 'route',
    ]
);

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'Skills',
    'Organisations',
    [
        \SkillDisplay\Skills\Controller\OrganisationController::class => 'list,show',
    ],
    // non-cacheable actions
    [
    ]
);

$apiActions = [
    \SkillDisplay\Skills\Controller\CertificationController::class => 'recent,recentRequests,modify,userCancel,listForVerifier,history,show,create,listForOrganisation',
    \SkillDisplay\Skills\Controller\SkillPathController::class => 'list,showApi,certificateDownload,syllabusForSetPdf,completeDownloadForSetPdf,progressForSet,getAwardsForSkillSet',
    \SkillDisplay\Skills\Controller\SkillController::class => 'show,skillUpAjax',
    \SkillDisplay\Skills\Controller\VerifierController::class => 'show,forSkill,listOfUser',
    \SkillDisplay\Skills\Controller\UserController::class => 'starCertifierAjax,show,countries,baseData,updatePassword,patrons,updateNotifications,updateEmail,updateSocialPlatforms,updateProfile,publicProfile,downloadPublicProfilePdf,publicProfileVerifications,getOrganizationsForCurrentUser,getAllAwards,updateAwardSelection',
    \SkillDisplay\Skills\Controller\OrganisationController::class => 'leave,joinOrganisation,removeMember,createInvitationCodesAjax,organisationStatistics,downloadCsvStatistics,show,setAccountOverdraw,managerList,getBillingInformation,verificationList',
    \SkillDisplay\Skills\Controller\SearchController::class => 'search',
    \SkillDisplay\Skills\Controller\CampaignController::class => 'getForUser',
    \SkillDisplay\Skills\Controller\PortalController::class => 'links',
    \SkillDisplay\Skills\Controller\VerificationCreditController::class => 'overview,list,add',
    \SkillDisplay\Skills\Controller\PaymentController::class => 'getSubscription, getCustomerPortalUrl',
    \SkillDisplay\Skills\Controller\NotificationController::class => 'show, deleteNotifications',
];

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'Skills',
    'Api',
    $apiActions,
    // non-cacheable actions
    $apiActions
);

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'Skills',
    'Anonymous',
    [
        \SkillDisplay\Skills\Controller\UserController::class => 'anonymousRequest, anonymousCreate',
    ],
    // non-cacheable actions
    [
        \SkillDisplay\Skills\Controller\UserController::class => 'anonymousRequest, anonymousCreate',
    ]
);

$GLOBALS['TYPO3_CONF_VARS']['LOG']['SkillDisplay']['Skills']['writerConfiguration'] = [
    \TYPO3\CMS\Core\Log\LogLevel::INFO => [
        \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
            'logFileInfix' => 'skills'
        ]
    ]
];

$caches = [
    'skill_progress',
    'skillset_progress'
];
foreach ($caches as $cacheName) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$cacheName] = [
        'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
        'backend' => \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class,
        'options' => [
            'defaultLifetime' => 2592000,
        ],
        'groups' => ['lowlevel']
    ];
}
