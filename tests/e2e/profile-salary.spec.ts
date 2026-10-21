import { expect, test } from '@playwright/test';
import { accounts, login } from './helpers';

test.describe('applicant profile CRUD and salary projection', () => {
  test.describe.configure({ mode: 'serial' });

  test.beforeEach(async ({ page }, testInfo) => {
    testInfo.skip(testInfo.project.name !== 'chromium-desktop', 'Profile mutations run once in Chromium.');
    await login(page, accounts.applicant);
    await page.goto('user/user_profile.php');
    await expect(page.locator('#profileMainContent')).toBeVisible();
  });

  test('personal information validates and address persists after refresh', async ({ page }) => {
    await page.locator('#editPersonalBtn').click();
    await page.locator('#profileFirstName').fill('Avery123');
    await expect(page.locator('#profileFirstName')).toHaveValue('Avery');
    await page.locator('#profilePhone').fill('123');
    await page.locator('#savePersonalBtn').click();
    await expect(page.locator('#saveConfirmModal')).toBeVisible();
    await page.locator('#saveConfirmModal').getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.locator('body')).toContainText('Invalid phone number');

    await page.locator('#profilePhone').fill('09171112222');
    await page.locator('#profileAddress').fill('E2E Updated Address, Bulacan');
    await page.locator('#savePersonalBtn').click();
    await page.locator('#saveConfirmModal').getByRole('button', { name: 'Save Changes' }).click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('#personalInfoView')).toContainText('E2E Updated Address');

    await page.reload();
    await expect(page.locator('#personalInfoView')).toContainText('E2E Updated Address');
  });

  test('personal phone number persists after saving', async ({ page }) => {
    test.fail(true, 'Known UI defect: confirmSave() reads the earlier hidden applicant_num field, so the visible phone value is submitted as empty.');
    await page.locator('#editPersonalBtn').click();
    await page.locator('#profilePhone').fill('09171112222');
    await page.locator('#savePersonalBtn').click();
    await page.locator('#saveConfirmModal').getByRole('button', { name: 'Save Changes' }).click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('#personalInfoView')).toContainText('09171112222');
    await page.reload();
    await expect(page.locator('#personalInfoView')).toContainText('09171112222');
  });

  test('Master’s unit boundaries and completion precedence persist through the backend', async ({ page }) => {
    const listResponse = await page.request.get('user/get_profile_lists.php?type=education');
    const list = await listResponse.json();
    const master = list.data.find((row: Record<string, unknown>) => row.education_level === 'master');
    expect(master?.id).toBeTruthy();

    const save = async (units: string, status = 'ongoing') => {
      const response = await page.request.post('user/save_profile_data.php', {
        form: {
          saveEducation: '1',
          edit_id: String(master.id),
          education_level: 'master',
          education_status: status,
          ed_degree: 'Master of Information Technology',
          ed_fs: 'Information Technology',
          ed_ins: 'Norzagaray College Test University',
          ed_sy: '2024',
          ed_ey: status === 'completed' ? '2026' : '',
          year_completed: status === 'completed' ? '2026' : '',
          ed_gpa: '1.25',
          completed_units: units,
        },
      });
      expect(response.status()).toBe(200);
      return response.json();
    };

    for (const [units, expected] of [[0, 150], [9, 150], [10, 150], [17, 150], [18, 170], [19, 170], [999, 170]] as const) {
      const result = await save(String(units));
      expect(result.success, JSON.stringify(result)).toBeTruthy();
      expect(Number(result.qualification_projection.applicable_hourly_rate)).toBe(expected);
    }

    for (const invalid of ['-1', '1.5', 'not-a-number']) {
      const result = await save(invalid);
      expect(result.success).toBeFalsy();
    }

    const completed = await save('0', 'completed');
    expect(completed.success).toBeTruthy();
    expect(Number(completed.qualification_projection.applicable_hourly_rate)).toBe(200);

    const reopened = await save('18', 'ongoing');
    expect(Number(reopened.qualification_projection.applicable_hourly_rate)).toBe(170);
    await page.reload();
    await page.locator('[data-profile-target="education"]').click();
    await expect(page.locator('#profileQualificationRate')).toContainText(/170\/hour/);

    await save('17', 'ongoing');
  });

  test('education add, edit, delete, cancel, and supporting-document controls work', async ({ page }) => {
    await page.locator('[data-profile-target="education"]').click();
    await page.locator('#addEducationBtn').click();
    await expect(page.locator('#educationModal')).toBeVisible();
    await page.locator('#cancelEducationBtn').click();
    await expect(page.locator('#educationModal')).toBeHidden();

    await page.locator('#addEducationBtn').click();
    await page.locator('#education_level').selectOption('bachelor');
    await page.locator('#education_status').selectOption('completed');
    await page.locator('#ed_degree').fill('Bachelor of E2E Quality Assurance');
    await page.locator('#ed_fs').fill('Software Testing');
    await page.locator('#ed_ins').fill('E2E University');
    await page.locator('#ed_sy').fill('2018');
    await page.locator('#ed_ey').fill('2022');
    await page.locator('#ed_gpa').fill('1.50');
    await page.locator('#saveEducationBtn').click();
    const record = page.locator('#educationList').locator('.ui-profile-record').filter({ hasText: 'Bachelor of E2E Quality Assurance' });
    await expect(record).toBeVisible();

    await record.getByRole('button', { name: /Edit/i }).click();
    await page.locator('#ed_degree').fill('Bachelor of E2E Automated Testing');
    await page.locator('#saveEducationBtn').click();
    await expect(page.locator('#educationList')).toContainText('Bachelor of E2E Automated Testing');
    await page.reload();
    await page.locator('[data-profile-target="education"]').click();
    const updated = page.locator('#educationList').locator('.ui-profile-record').filter({ hasText: 'Bachelor of E2E Automated Testing' });
    await expect(updated).toBeVisible();
    await updated.getByRole('button', { name: /Delete/i }).click();
    await page.locator('#confirmDeleteBtn').click();
    await expect(page.locator('#educationList')).not.toContainText('Bachelor of E2E Automated Testing');
  });

  test('work experience, skill, and qualification records support add and delete', async ({ page }) => {
    await page.locator('[data-profile-target="experience"]').click();
    await page.locator('#addExperienceBtn').click();
    await page.locator('#experience_type').selectOption('industry');
    await page.locator('#job_title').fill('E2E QA Engineer');
    await page.locator('#work_comp').fill('E2E Labs');
    await page.locator('#work_loc').fill('Bulacan');
    await page.locator('#start_date').fill('2024-01');
    await page.locator('#end_date').fill('2025-01');
    await page.locator('#work_descript').fill('Automated full-stack quality checks.');
    await page.locator('#saveExperienceBtn').click();
    const experience = page.locator('#experienceList').locator('.ui-profile-record').filter({ hasText: 'E2E QA Engineer' });
    await expect(experience).toBeVisible();
    await experience.getByRole('button', { name: /Delete/i }).click();
    await page.locator('#confirmDeleteBtn').click();
    await expect(page.locator('#experienceList')).not.toContainText('E2E QA Engineer');

    await page.locator('[data-profile-target="skills"]').click();
    await page.locator('#addSkillBtn').click();
    await page.locator('#skill_name').fill('Playwright Automation');
    await page.locator('.skill-level[data-level="5"]').click();
    await page.locator('#saveSkillBtn').click();
    const skill = page.locator('#skillsList').locator('.ui-skill-item').filter({ hasText: 'Playwright Automation' });
    await expect(skill).toBeVisible();
    await skill.getByRole('button', { name: /Delete Playwright Automation/i }).click();
    await page.locator('#confirmDeleteBtn').click();
    await expect(page.locator('#skillsList')).not.toContainText('Playwright Automation');

    await page.locator('[data-profile-target="qualifications"]').click();
    await expect(page.locator('#qualificationCount')).toHaveText('1 credential');
    await page.locator('#addQualificationBtn').click();
    await page.locator('#qualification_type').selectOption('license');
    await page.locator('#qualification_title').fill('E2E Professional License');
    await page.locator('#issuing_organization').fill('E2E Authority');
    await page.locator('#issued_date').fill('2026-01-15');
    await page.locator('#saveQualificationBtn').click();
    const qualification = page.locator('#qualificationsList').locator('.ui-profile-record').filter({ hasText: 'E2E Professional License' });
    await expect(qualification).toBeVisible();
    await expect(page.locator('#qualificationCount')).toHaveText('2 credentials');
    await qualification.getByRole('button', { name: /More actions/i }).click();
    await qualification.getByRole('menuitem', { name: /Delete/i }).click();
    await page.locator('#confirmDeleteBtn').click();
    await expect(page.locator('#qualificationsList')).not.toContainText('E2E Professional License');
    await expect(page.locator('#qualificationCount')).toHaveText('1 credential');
  });

  test('credential cards stay inside a mobile viewport', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.locator('#profileSectionSelect').selectOption('qualifications');
    const card = page.locator('#qualificationsList .ui-credential-card').first();
    await expect(card).toBeVisible();
    const bounds = await card.boundingBox();
    expect(bounds).not.toBeNull();
    expect(bounds!.x).toBeGreaterThanOrEqual(0);
    expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(390);
  });
});

