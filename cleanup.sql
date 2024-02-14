
# cleanup
delete FROM `tx_skills_domain_model_brand` WHERE deleted = 1;
delete FROM `tx_skills_domain_model_certifier` WHERE deleted = 1;
delete FROM `tx_skills_domain_model_shortlink` WHERE deleted = 1;
delete FROM `tx_skills_domain_model_skill` WHERE deleted = 1;
delete FROM `tx_skills_domain_model_skillpath` WHERE deleted = 1;
delete FROM `tx_skills_domain_model_skillgroup` WHERE deleted = 1;

delete FROM `tx_skills_domain_model_certification` WHERE not exists (select uid from fe_users where user = uid);
delete FROM `tx_skills_domain_model_certification` WHERE not exists (select uid from tx_skills_domain_model_skill where skill = uid);
delete FROM `tx_skills_domain_model_certifier` WHERE not exists (select uid from fe_users where user = uid);
delete FROM `tx_skills_domain_model_certifier` WHERE not exists (select uid from tx_skills_domain_model_brand where brand = uid);
delete FROM `tx_skills_domain_model_certifierpermission` WHERE not exists (select uid from tx_skills_domain_model_certifier where certifier = uid);
delete FROM `tx_skills_domain_model_certifierpermission` WHERE not exists (select uid from tx_skills_domain_model_skill where skill = uid);
delete FROM `tx_skills_domain_model_grantedreward` WHERE not exists (select uid from fe_users where user = uid);
delete `tx_skills_domain_model_grantedreward` FROM `tx_skills_domain_model_grantedreward` LEFT JOIN tx_skills_domain_model_reward r ON r.uid = tx_skills_domain_model_grantedreward.reward WHERE r.uid is NULL;
delete FROM `tx_skills_domain_model_link` WHERE tablename = 'tx_skills_domain_model_skill' and not exists (select uid from tx_skills_domain_model_skill where skill = uid);
delete FROM `tx_skills_domain_model_link` WHERE tablename = 'tx_skills_domain_model_skillpath' and not exists (select uid from tx_skills_domain_model_skillpath where skill = uid);
delete FROM `tx_skills_domain_model_requirement` WHERE not exists (select uid from tx_skills_domain_model_skill where skill = uid);
delete `tx_skills_domain_model_rewardprerequisite` FROM `tx_skills_domain_model_rewardprerequisite` LEFT JOIN tx_skills_domain_model_reward r ON r.uid = tx_skills_domain_model_rewardprerequisite.reward WHERE r.uid is NULL;
delete FROM `tx_skills_domain_model_rewardprerequisite` WHERE not exists (select uid from tx_skills_domain_model_skill where skill = uid);
delete FROM `tx_skills_domain_model_set` WHERE not exists (select uid from tx_skills_domain_model_requirement where requirement = uid);
delete FROM `tx_skills_domain_model_setskill` WHERE not exists (select uid from tx_skills_domain_model_skill where skill = uid);
delete FROM `tx_skills_domain_model_setskill` WHERE not exists (select uid from tx_skills_domain_model_set where tx_set = uid);

delete FROM `tx_skills_skill_tag_mm` WHERE not exists (select uid from tx_skills_domain_model_skill where uid_local = uid);
delete FROM `tx_skills_skill_tag_mm` WHERE not exists (select uid from tx_skills_domain_model_tag where uid_foreign = uid);
delete FROM `tx_skills_skill_brand_mm` WHERE not exists (select uid from tx_skills_domain_model_skill where uid_local = uid);
delete FROM `tx_skills_skill_brand_mm` WHERE not exists (select uid from tx_skills_domain_model_brand where uid_foreign = uid);
delete FROM `tx_skills_skillset_brand_mm` WHERE not exists (select uid from tx_skills_domain_model_skillpath where uid_local = uid);
delete FROM `tx_skills_skillset_brand_mm` WHERE not exists (select uid from tx_skills_domain_model_brand where uid_foreign = uid);
delete from `tx_skills_skillpath_skill_mm` WHERE not exists (select uid from tx_skills_domain_model_skillpath where uid_local = uid);
delete from `tx_skills_skillpath_skill_mm` WHERE not exists (select uid from tx_skills_domain_model_skill where uid_foreign = uid);
delete from `tx_skills_skillgroup_skill_mm` WHERE not exists (select uid from tx_skills_domain_model_skillgroup where uid_local = uid);
delete from `tx_skills_skillgroup_skill_mm` WHERE not exists (select uid from tx_skills_domain_model_skill where uid_foreign = uid);
delete FROM `tx_skills_user_brand_mm` WHERE not exists (select uid from fe_users where uid_local = uid);
delete FROM `tx_skills_user_brand_mm` WHERE not exists (select uid from tx_skills_domain_model_brand where uid_foreign = uid);
delete FROM `tx_skills_user_certifier_mm` WHERE not exists (select uid from fe_users where uid_local = uid);
delete FROM `tx_skills_user_certifier_mm` WHERE not exists (select uid from tx_skills_domain_model_certifier where uid_foreign = uid);
delete FROM `tx_skills_user_organisation_mm` WHERE not exists (select uid from fe_users where uid_local = uid);
delete FROM `tx_skills_user_organisation_mm` WHERE not exists (select uid from tx_skills_domain_model_brand where uid_foreign = uid);
delete FROM `tx_skills_patron_mm` WHERE not exists (select uid from tx_skills_domain_model_brand where uid_local = uid);
delete FROM `tx_skills_patron_mm` WHERE not exists (select uid from tx_skills_domain_model_brand where uid_foreign = uid);

##########################################################################################

# user removal
delete from fe_users where lastlogin <= 1543795200;

# find unskilled skills
select s.uid, s.title
from tx_skills_domain_model_skill s
left join tx_skills_domain_model_certification c on c.skill = s.uid
where c.uid is null and s.sys_language_uid = 0
group by s.uid, s.title
order by s.title;

# skill requirements
SELECT s.uid AS 'Skill UID', s.title AS 'Skill Title', req.uid AS 'Required Skill UID', req.title AS 'Required Skill title'
FROM `tx_skills_domain_model_skill` s
			 LEFT JOIN tx_skills_domain_model_requirement r ON s.uid = r.skill
			 JOIN tx_skills_domain_model_set ss ON r.uid = ss.requirement
			 JOIN tx_skills_domain_model_setskill sk ON ss.uid = sk.tx_set
			 JOIN tx_skills_domain_model_skill req ON req.uid = sk.skill
WHERE s.deleted =0
ORDER BY s.uid, req.uid;

# skillup stats
SELECT c.uid as vid, c.tier1 as cert, c.tier2 as edu, c.tier3 as self, c.tier4 as business, c.grant_date, s.title as skillTitle, u.last_name, u.first_name, from_unixtime(c.crdate) as requestDate
FROM `tx_skills_domain_model_certification` c
			 join tx_skills_domain_model_skill s ON c.skill = s.uid
			 join fe_users u on c.user = u.uid
where c.grant_date >= '2018-09-24' and c.grant_date <= '2018-09-26' and c.deny_date is null and c.revoke_date is null
	and exists (select * from tx_skills_user_organisation_mm m where m.uid_local = u.uid and m.uid_foreign = 25)
ORDER BY c.crdate;

# verifications with missing relations
SELECT c.uid as "verification uid", u.username, s.title as "skill name", b.name as "brand name", c.certifier as "verifier id", u2.username as "verifier", c.campaign as "campaign id", n.title as "campaign" FROM `tx_skills_domain_model_certification` c
left JOIN fe_users u on u.uid = c.user
left JOIN tx_skills_domain_model_skill s on s.uid = c.skill
left JOIN tx_skills_domain_model_brand b on b.uid = c.brand
left JOIN tx_skills_domain_model_certifier v on v.uid = c.certifier
left JOIN fe_users u2 on u2.uid = v.user
left JOIN tx_skills_domain_model_campaign n on n.uid = c.campaign
WHERE (c.brand <> 0 and not exists (select uid from tx_skills_domain_model_brand where c.brand = uid))
or (c.certifier <> 0 and not exists (select uid from tx_skills_domain_model_certifier where c.certifier = uid))
#or (campaign <> 0 and not exists (select uid from tx_skills_domain_model_certification where campaign = uid))
order by s.title;

# tables with duplicate uuids
select * from tx_skills_domain_model_link group by uuid having count(*) > 1 or uuid = '' or uuid is null;
select * from tx_skills_domain_model_tag group by uuid having count(*) > 1 or uuid = '' or uuid is null;
select * from tx_skills_domain_model_brand where deleted = 0 group by uuid having count(*) > 1 or uuid = '' or uuid is null;
select * from tx_skills_domain_model_skill where deleted = 0 group by uuid having count(*) > 1 or uuid = '' or uuid is null;
select * from tx_skills_domain_model_skillpath where deleted = 0 group by uuid having count(*) > 1 or uuid = '' or uuid is null;

