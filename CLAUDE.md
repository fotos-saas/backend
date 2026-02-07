# backend

Project documentation.

## ACE Learned Strategies

<!-- ACE:START - Do not edit manually -->
skills[5	]{id	section	content	helpful	harmful	neutral}:
  deployment-00001	deployment	Find Coolify container by git commit hash in image tag for artisan	1	0	0
  deployment-00002	deployment	Ignore fotos-* containers for Laravel artisan; use Coolify sk8g* containers	1	0	0
  project_structure-00003	project_structure	Backend and frontend are separate git repos; commit from each subdirectory	1	0	0
  testing-00004	testing	Use `php -l` for local syntax verification; tests need Docker postgres	1	0	0
  project_structure-00005	project_structure	Services use domain subdirectories (Services/Tablo/); resolve paths via Glob first	1	0	0
  branding_flow-00006	branding_flow	Partner branding data must be in ALL session responses (login + validate-session); validate-session overwrites project data on page reload	1	0	0
  partner_resolution-00007	partner_resolution	TabloPartner→Partner link uses partner_id FK (priority) + email fallback (legacy); update ResolvesPartner, CheckPartnerFeature, User.getEffectivePartner when changing	1	0	0
  feature_gate-00008	feature_gate	CheckPartnerFeature middleware: checkTabloPartnerFeature must check subscriber Partner for ALL features, not just forum/polls	1	0	0
<!-- ACE:END -->
