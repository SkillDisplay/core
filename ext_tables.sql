

CREATE TABLE sys_category
(
	icon        int(11) unsigned default '0' NOT NULL,
	description text
);

CREATE TABLE tx_skills_domain_model_campaign
(
	title varchar(255)     DEFAULT ''  NOT NULL,
	user  int(11) unsigned DEFAULT '0' NOT NULL,

	KEY user (user)
);

CREATE TABLE tx_skills_domain_model_invitationcode
(
	code       varchar(255)     DEFAULT ''  NOT NULL,
	brand      int(11) unsigned DEFAULT '0' NOT NULL,
	expires    int(11) unsigned DEFAULT '0' NOT NULL,
	created_by int(11) unsigned DEFAULT '0' NOT NULL,
	used_by    int(11) unsigned DEFAULT '0' NOT NULL,
	used_at    int(11) unsigned DEFAULT '0' NOT NULL
);

CREATE TABLE tx_skills_domain_model_shortlink
(
	hash       varchar(255) DEFAULT '' NOT NULL,
	action     varchar(255) DEFAULT '' NOT NULL,
	parameters varchar(255) DEFAULT '' NOT NULL
);

CREATE TABLE fe_users
(
	anonymous            tinyint(1) unsigned DEFAULT '0'              NOT NULL,
	terms_accepted       int(11)             DEFAULT '0'              NOT NULL,
	locked               tinyint(1) unsigned DEFAULT '0'              NOT NULL,
	pending_email        varchar(100)        DEFAULT ''               NOT NULL,

	newsletter           tinyint(1) unsigned DEFAULT '0'              NOT NULL,

	avatar               int(11) unsigned    DEFAULT '0'              NOT NULL,
	profile_link         varchar(100)        DEFAULT ''               NOT NULL,
	twitter              varchar(100)        DEFAULT ''               NOT NULL,
	linkedin             varchar(100)        DEFAULT ''               NOT NULL,
	github               varchar(100)        DEFAULT ''               NOT NULL,
	xing                 varchar(100)        DEFAULT ''               NOT NULL,

	favourite_certifiers int(11) unsigned    DEFAULT '0'              NOT NULL,
	managed_brands       int(11) unsigned    DEFAULT '0'              NOT NULL,
	organisations        int(11) unsigned    DEFAULT '0'              NOT NULL,

	publish_skills       tinyint(1) unsigned DEFAULT '0'              NOT NULL,
	mail_push            tinyint(1) unsigned DEFAULT '1'              NOT NULL,
	mail_language        varchar(2)          DEFAULT 'en'             NOT NULL,

	verifiers            int(11) unsigned    DEFAULT '0'              NOT NULL,
	api_key              varchar(100)        DEFAULT ''               NOT NULL,
	monthly_activity     varchar(200)        DEFAULT ''               NOT NULL,

	foreign_username     varchar(200)        DEFAULT ''               NOT NULL,
	data_sync            int(11)             DEFAULT '0'              NOT NULL,
	tx_extbase_type      varchar(255)        DEFAULT 'Tx_Skills_User' NOT NULL
);

CREATE TABLE tx_skills_domain_model_tag
(
	title                varchar(255)        DEFAULT ''  NOT NULL,
	description          text,
	uuid                 varchar(40),
	imported             int(11) unsigned    DEFAULT '0' NOT NULL,
	domain_tag           tinyint(4) unsigned DEFAULT '0' NOT NULL,

	tagged_skills        int(11) unsigned    DEFAULT '0' NOT NULL,
	domain_tagged_skills int(11) unsigned    DEFAULT '0' NOT NULL
);

CREATE TABLE tx_skills_domain_model_link
(
	skill     int(11) unsigned DEFAULT '0'                            NOT NULL,
	tablename varchar(255)     DEFAULT 'tx_skills_domain_model_skill' NOT NULL,
	sorting   int(11)          DEFAULT '0'                            NOT NULL,

	title     varchar(255)     DEFAULT ''                             NOT NULL,
	url       varchar(255)     DEFAULT ''                             NOT NULL,
	color     varchar(10)      DEFAULT ''                             NOT NULL,
	uuid      varchar(40),
	imported  int(11) unsigned DEFAULT '0'                            NOT NULL,

	KEY assoc (tablename, skill)
);

CREATE TABLE tx_skills_domain_model_brand
(
	name                     varchar(255)        DEFAULT ''  NOT NULL,
	description              text,
	logo                     int(11) unsigned    DEFAULT '0' NOT NULL,
	banner                   int(11) unsigned    DEFAULT '0' NOT NULL,
	pixel_logo               int(11) unsigned    DEFAULT '0' NOT NULL,
	url                      varchar(255)        DEFAULT ''  NOT NULL,
	categories               int(11) unsigned    DEFAULT '0' NOT NULL,
	patronages               int(11) unsigned    DEFAULT '0' NOT NULL,
	patronage_level          int(11) unsigned    DEFAULT '0' NOT NULL,
	partner_level            int(11) unsigned    DEFAULT '0' NOT NULL,
	show_num_of_certificates tinyint(4) unsigned DEFAULT '0' NOT NULL,
	members                  int(11) unsigned    DEFAULT '0' NOT NULL,
	uuid                     varchar(40),
	imported                 int(11) unsigned    DEFAULT '0' NOT NULL,
	show_in_search           tinyint(4) unsigned DEFAULT '1' NOT NULL,

	created_by_brand         varchar(255)        DEFAULT ''  NOT NULL,
	credit_overdraw          tinyint(4) unsigned DEFAULT '0' NOT NULL,
	billable                 tinyint(1) unsigned DEFAULT '1' NOT NULL,
	api_key                  varchar(100)        DEFAULT ''  NOT NULL,
	billing_address          text,
	country                  varchar(3)          DEFAULT ''  NOT NULL,
	vat_id                   varchar(20)         DEFAULT ''  NOT NULL,
	foreign_id               varchar(200)        DEFAULT ''  NOT NULL
);


CREATE TABLE tx_skills_domain_model_skillpath
(
	name                    varchar(255)        DEFAULT ''    NOT NULL,
	path_segment            varchar(2048),
	description             text,
	brands                  int(11) unsigned    DEFAULT '0'   NOT NULL,
	media                   int(11) unsigned    DEFAULT '0'   NOT NULL,
	skills                  int(11) unsigned    DEFAULT '0'   NOT NULL,
	links                   int(11) unsigned    DEFAULT '0'   NOT NULL,
	legitimation_user       int(11) unsigned    DEFAULT '0'   NOT NULL,
	legitimation_date       int(11) unsigned    DEFAULT '0'   NOT NULL,
	uuid                    varchar(40),
	imported                int(11) unsigned    DEFAULT '0'   NOT NULL,
	syllabus_layout_file    int(11)             DEFAULT '0'   NOT NULL,
	visibility              tinyint(4) unsigned DEFAULT '1'   NOT NULL,

	certificate_link        varchar(255)        DEFAULT ''    NOT NULL,
	certificate_layout_file int(11)             DEFAULT '0'   NOT NULL,
	popularity_log2         float               DEFAULT '0.0' NOT NULL,

	KEY path_segment (path_segment(185), uid)
);

CREATE TABLE tx_skills_domain_model_skillgroup
(
	name                        varchar(255)     DEFAULT ''  NOT NULL,
	description                 text,
	skills                      int(11) unsigned DEFAULT '0' NOT NULL,
	links                       int(11) unsigned DEFAULT '0' NOT NULL,
	skillup_comment_placeholder varchar(255)     DEFAULT ''  NOT NULL,
	skillup_comment_preset      varchar(255)     DEFAULT ''  NOT NULL
);

CREATE TABLE tx_skills_domain_model_skill
(
	title        varchar(255)        DEFAULT ''  NOT NULL,
	path_segment varchar(2048),
	goals        text,
	description  text,
	icon         varchar(255)        DEFAULT ''  NOT NULL,
	image        int(11) unsigned    DEFAULT '0' NOT NULL,
	placeholder  tinyint(1) unsigned DEFAULT '0' NOT NULL,
	dormant      int(11) unsigned    DEFAULT '0' NOT NULL,
	brands       int(11) unsigned    DEFAULT '0' NOT NULL,
	owner        int(11) unsigned    DEFAULT '0' NOT NULL,
	tags         int(11) unsigned    DEFAULT '0' NOT NULL,
	domain_tag   int(11) unsigned    DEFAULT '0' NOT NULL,
	links        int(11) unsigned    DEFAULT '0' NOT NULL,
	requirements int(11) unsigned    DEFAULT '0' NOT NULL,
	uuid         varchar(40),
	imported     int(11) unsigned    DEFAULT '0' NOT NULL,
	int_notes    text,
	visibility   tinyint(4) unsigned DEFAULT '1' NOT NULL,

	KEY path_segment (path_segment(185), uid)
);

CREATE TABLE tx_skills_domain_model_requirement
(
	skill   int(11) unsigned DEFAULT '0' NOT NULL,
	sets    int(11) unsigned DEFAULT '0' NOT NULL,
	sorting int(11)          DEFAULT '0' NOT NULL,

	KEY skill (skill)
);

CREATE TABLE tx_skills_domain_model_set
(
	requirement int(11) unsigned DEFAULT '0' NOT NULL,
	skills      int(11) unsigned DEFAULT '0' NOT NULL,
	sorting     int(11)          DEFAULT '0' NOT NULL,

	KEY requirement (requirement)
);

CREATE TABLE tx_skills_domain_model_setskill
(
	tx_set  int(11) unsigned DEFAULT '0' NOT NULL,
	skill   int(11) unsigned DEFAULT '0',
	sorting int(11)          DEFAULT '0' NOT NULL,

	KEY tx_set (tx_set)
);

CREATE TABLE tx_skills_domain_model_certifier
(
	user              int(11) unsigned DEFAULT '0',
	brand             int(11) unsigned DEFAULT '0',
	permissions       int(11) unsigned DEFAULT '0' NOT NULL,
	link              varchar(255)     DEFAULT ''  NOT NULL,
	shared_api_secret varchar(100)     DEFAULT ''  NOT NULL,
	test_system       varchar(255)     DEFAULT '',

	KEY user (user),
	KEY brand (brand)
);

CREATE TABLE tx_skills_domain_model_certifierpermission
(
	certifier int(11) unsigned    DEFAULT '0' NOT NULL,

	tier1     tinyint(1) unsigned DEFAULT '0' NOT NULL,
	tier2     tinyint(1) unsigned DEFAULT '0' NOT NULL,
	tier4     tinyint(1) unsigned DEFAULT '0' NOT NULL,
	skill     int(11) unsigned    DEFAULT '0',

	KEY skill (skill),
	KEY certifier (certifier)
);

CREATE TABLE tx_skills_domain_model_certification
(
	tier1            tinyint(1) unsigned DEFAULT '0'   NOT NULL,
	tier2            tinyint(1) unsigned DEFAULT '0'   NOT NULL,
	tier3            tinyint(1) unsigned DEFAULT '0'   NOT NULL,
	tier4            tinyint(1) unsigned DEFAULT '0'   NOT NULL,
	grant_date       datetime            DEFAULT NULL,
	deny_date        datetime            DEFAULT NULL,
	expire_date      datetime            DEFAULT NULL,
	revoke_date      datetime            DEFAULT NULL,
	revoke_reason    text,

	skill            int(11) unsigned    DEFAULT '0'   NOT NULL,
	user             int(11) unsigned    DEFAULT '0'   NOT NULL,
	certifier        int(11) unsigned    DEFAULT '0'   NOT NULL,
	brand            int(11) unsigned    DEFAULT '0'   NOT NULL,
	campaign         int(11) unsigned    DEFAULT '0'   NOT NULL,
	request_group    varchar(50)         DEFAULT ''    NOT NULL,
	rewardable       tinyint(1) unsigned DEFAULT '1'   NOT NULL,
	comment          text,

	# book keeping value duplicates for history reasons
	skill_title      varchar(255)        DEFAULT ''    NOT NULL,
	user_username    varchar(255)        DEFAULT ''    NOT NULL,
	user_firstname   varchar(100)        DEFAULT ''    NOT NULL,
	user_lastname    varchar(100)        DEFAULT ''    NOT NULL,
	verifier_name    varchar(200)        DEFAULT ''    NOT NULL,
	brand_name       varchar(255)        DEFAULT ''    NOT NULL,
	group_name       varchar(255)        DEFAULT ''    NOT NULL,
	user_memberships int(11) unsigned    DEFAULT '0'   NOT NULL,

	points           int(11) unsigned    DEFAULT '0'   NOT NULL,
	price            decimal(10, 2)      DEFAULT '0.0' NOT NULL,

	KEY user (user),
	KEY skill (skill),
	KEY certifier (certifier),
	KEY user_skill (user, skill),
	KEY accepted (user, grant_date, revoke_date)
);

CREATE TABLE tx_skills_domain_model_reward
(

	title                  varchar(255)        DEFAULT ''  NOT NULL,
	category               int(11) unsigned    DEFAULT '0' NOT NULL,
	type                   varchar(20)         DEFAULT ''  NOT NULL,
	brand                  int(11) unsigned    DEFAULT '0' NOT NULL,
	reward                 varchar(255)        DEFAULT ''  NOT NULL,
	pdf_layout_file        int(11)             DEFAULT '0' NOT NULL,
	syllabus_layout_file   int(11)             DEFAULT '0' NOT NULL,
	description            varchar(255)        DEFAULT ''  NOT NULL,
	detail_link            varchar(255)        DEFAULT ''  NOT NULL,

	availability_start     int(11) unsigned    DEFAULT '0' NOT NULL,
	availability_end       int(11) unsigned    DEFAULT '0' NOT NULL,
	valid_for_organisation int(11) unsigned    DEFAULT '0' NOT NULL,
	valid_until            int(11) unsigned    DEFAULT '0' NOT NULL,

	prerequisites          int(11) unsigned    DEFAULT '0' NOT NULL,
	skillpath              int(11) unsigned    DEFAULT '0' NOT NULL,
	level                  int(11) unsigned    DEFAULT '0' NOT NULL,
	active                 tinyint(1) unsigned DEFAULT '0' NOT NULL,

	KEY skillpath (skillpath)
);

CREATE TABLE tx_skills_domain_model_rewardprerequisite
(
	reward int(11) unsigned DEFAULT '0' NOT NULL,
	skill  int(11) unsigned DEFAULT '0' NOT NULL,
	level  int(11) unsigned DEFAULT '0' NOT NULL,
	brand  int(11) unsigned DEFAULT '0' NOT NULL
);

CREATE TABLE tx_skills_domain_model_grantedreward
(
	reward           int(11) unsigned    DEFAULT '0' NOT NULL,
	user             int(11) unsigned    DEFAULT '0' NOT NULL,
	valid_until      int(11) unsigned    DEFAULT '0' NOT NULL,
	selected_by_user tinyint(1) unsigned DEFAULT '0' NOT NULL,
	position_index   int(11) unsigned    DEFAULT '0' NOT NULL
);

CREATE TABLE tx_skills_domain_model_reward_activation
(
	reward int(11) unsigned    DEFAULT '0' NOT NULL,
	active tinyint(1) unsigned DEFAULT '0' NOT NULL
);

CREATE TABLE tx_skills_domain_model_award
(
	user   int(11) unsigned DEFAULT '0',
	brand  int(11) unsigned DEFAULT '0',
	type   int(11) unsigned DEFAULT '0',
	level  int(11) unsigned DEFAULT '1',
	`rank` int(11) unsigned DEFAULT '0'
);

CREATE TABLE tx_skills_domain_model_organisationstatistics
(
	brand                       int(11) unsigned DEFAULT '0',
	total_score                 int(11) unsigned DEFAULT '0',
	current_month_users         int(11) unsigned DEFAULT '0',
	last_month_users            int(11) unsigned DEFAULT '0',
	current_month_verifications int(11) unsigned DEFAULT '0',
	sum_verifications           int(11) unsigned DEFAULT '0',
	sum_supported_skills        int(11) unsigned DEFAULT '0',
	sum_skills                  int(11) unsigned DEFAULT '0',
	last_month_verifications    int(11) unsigned DEFAULT '0',
	current_month_issued        int(11) unsigned DEFAULT '0',
	last_month_issued           int(11) unsigned DEFAULT '0',
	sum_issued                  int(11) unsigned DEFAULT '0',
	expertise                   varchar(2000)    DEFAULT '' NOT NULL,
	monthly_scores              varchar(200)     DEFAULT '' NOT NULL,
	interests                   varchar(200)     DEFAULT '' NOT NULL,
	potential                   varchar(2000)    DEFAULT '' NOT NULL,
	composition                 varchar(2000)    DEFAULT '' NOT NULL,

	KEY brand (brand)
);

CREATE TABLE tx_skills_domain_model_membershiphistory
(
	verification int(11) unsigned DEFAULT '0' NOT NULL,
	brand        int(11) unsigned DEFAULT '0' NOT NULL,
	brand_name   varchar(255)     DEFAULT ''  NOT NULL,

	KEY verification (verification)
);


# Patronages
CREATE TABLE tx_skills_patron_mm
(
	uid_local       int(11) unsigned DEFAULT '0' NOT NULL,
	uid_foreign     int(11) unsigned DEFAULT '0' NOT NULL,
	sorting         int(11) unsigned DEFAULT '0' NOT NULL,
	sorting_foreign int(11) unsigned DEFAULT '0' NOT NULL,

	KEY uid_local (uid_local),
	KEY uid_foreign (uid_foreign)
);

# skills in skillset
CREATE TABLE tx_skills_skillpath_skill_mm
(
	uid_local       int(11) unsigned DEFAULT '0' NOT NULL,
	uid_foreign     int(11) unsigned DEFAULT '0' NOT NULL,
	sorting         int(11) unsigned DEFAULT '0' NOT NULL,
	sorting_foreign int(11) unsigned DEFAULT '0' NOT NULL,

	KEY uid_local (uid_local),
	KEY uid_foreign (uid_foreign)
);

# Skills in skillgroup
CREATE TABLE tx_skills_skillgroup_skill_mm
(
	uid_local       int(11) unsigned DEFAULT '0' NOT NULL,
	uid_foreign     int(11) unsigned DEFAULT '0' NOT NULL,
	sorting         int(11) unsigned DEFAULT '0' NOT NULL,
	sorting_foreign int(11) unsigned DEFAULT '0' NOT NULL,

	KEY uid_local (uid_local),
	KEY uid_foreign (uid_foreign)
);

# Brands of skill
CREATE TABLE tx_skills_skill_brand_mm
(
	uid_local       int(11) unsigned DEFAULT '0' NOT NULL,
	uid_foreign     int(11) unsigned DEFAULT '0' NOT NULL,
	sorting         int(11) unsigned DEFAULT '0' NOT NULL,
	sorting_foreign int(11) unsigned DEFAULT '0' NOT NULL,

	KEY uid_local (uid_local),
	KEY uid_foreign (uid_foreign)
);

# Brands of skillset
CREATE TABLE tx_skills_skillset_brand_mm
(
	uid_local       int(11) unsigned DEFAULT '0' NOT NULL,
	uid_foreign     int(11) unsigned DEFAULT '0' NOT NULL,
	sorting         int(11) unsigned DEFAULT '0' NOT NULL,
	sorting_foreign int(11) unsigned DEFAULT '0' NOT NULL,

	KEY uid_local (uid_local),
	KEY uid_foreign (uid_foreign)
);

# Tags of skill
CREATE TABLE tx_skills_skill_tag_mm
(
	uid_local       int(11) unsigned DEFAULT '0' NOT NULL,
	uid_foreign     int(11) unsigned DEFAULT '0' NOT NULL,
	sorting         int(11) unsigned DEFAULT '0' NOT NULL,
	sorting_foreign int(11) unsigned DEFAULT '0' NOT NULL,

	KEY uid_local (uid_local),
	KEY uid_foreign (uid_foreign)
);

# Verifier roles
CREATE TABLE tx_skills_user_certifier_mm
(
	uid_local       int(11) unsigned DEFAULT '0' NOT NULL,
	uid_foreign     int(11) unsigned DEFAULT '0' NOT NULL,
	sorting         int(11) unsigned DEFAULT '0' NOT NULL,
	sorting_foreign int(11) unsigned DEFAULT '0' NOT NULL,

	KEY uid_local (uid_local),
	KEY uid_foreign (uid_foreign)
);

# Organisation manager
CREATE TABLE tx_skills_user_brand_mm
(
	uid_local       int(11) unsigned DEFAULT '0' NOT NULL,
	uid_foreign     int(11) unsigned DEFAULT '0' NOT NULL,
	sorting         int(11) unsigned DEFAULT '0' NOT NULL,
	sorting_foreign int(11) unsigned DEFAULT '0' NOT NULL,

	KEY uid_local (uid_local),
	KEY uid_foreign (uid_foreign)
);

# Organisation membership
CREATE TABLE tx_skills_user_organisation_mm
(
	uid_local       int(11) unsigned DEFAULT '0' NOT NULL,
	uid_foreign     int(11) unsigned DEFAULT '0' NOT NULL,
	sorting         int(11) unsigned DEFAULT '0' NOT NULL,
	sorting_foreign int(11) unsigned DEFAULT '0' NOT NULL,

	KEY uid_local (uid_local),
	KEY uid_foreign (uid_foreign)
);

CREATE TABLE tx_skills_domain_model_verificationcreditpack
(
	valuta         int(11) unsigned                NOT NULL,
	valid_thru     int(11) unsigned DEFAULT '0'    NOT NULL,
	brand          int(11) unsigned                NOT NULL,
	title          varchar(200)                    NOT NULL,
	current_points int(11) unsigned DEFAULT '0'    NOT NULL,
	initial_points int(11) unsigned DEFAULT '0'    NOT NULL,
	price          decimal(10, 2)   DEFAULT '0.00' NOT NULL,
	price_charged  decimal(10, 2)   DEFAULT '0.00' NOT NULL,
	user           int(11) unsigned DEFAULT '0'    NOT NULL,
	invoice_number varchar(100)     DEFAULT ''     NOT NULL,

	# book keeping value duplicates for history reasons
	brand_name     varchar(255)                    NOT NULL,
	user_username  varchar(255)     DEFAULT ''     NOT NULL,
	user_firstname varchar(50)      DEFAULT ''     NOT NULL,
	user_lastname  varchar(50)      DEFAULT ''     NOT NULL,

	KEY brand (brand)
);

CREATE TABLE tx_skills_domain_model_verificationcreditusage
(
	credit_pack  int(11) unsigned NOT NULL,
	verification int(11) unsigned NOT NULL,
	points       int(11) unsigned NOT NULL,
	price        decimal(10, 2)   NOT NULL,

	KEY verification (verification)
);

# recommended skillsets for skillset/skill
CREATE TABLE tx_skills_domain_model_recommendedskillset
(
	type                 tinyint(3) unsigned DEFAULT '0'   NOT NULL,
	user                 int(11) unsigned    DEFAULT '0'   NOT NULL,
	source_skillset      int(11) unsigned    DEFAULT '0'   NOT NULL,
	source_skill         int(11) unsigned    DEFAULT '0'   NOT NULL,
	recommended_skillset int(11) unsigned    DEFAULT '0'   NOT NULL,
	jaccard              float               DEFAULT '0.0' NOT NULL,
	score                float               DEFAULT '0.0' NOT NULL,

	PRIMARY KEY (type, user, source_skillset, source_skill, recommended_skillset),
	KEY byuser (user, source_skillset),
	KEY bytypeuser (type, user, source_skillset)
);

# table for skilldisplay notifications
CREATE TABLE tx_skills_domain_model_notification
(
	user      int(11) unsigned DEFAULT '0' NOT NULL,
	type      varchar(50)      DEFAULT ''  NOT NULL,
	reference varchar(255)     DEFAULT ''  NOT NULL,
	message   varchar(255)     DEFAULT ''  NOT NULL,

	KEY user (user)
);
