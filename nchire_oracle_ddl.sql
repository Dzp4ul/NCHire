-- NCHire Oracle DDL - Generated from MySQL schema
-- Compatible with Oracle Data Modeler import

-- ============================================================
-- SEQUENCES (Oracle equivalent of MySQL AUTO_INCREMENT)
-- ============================================================

CREATE SEQUENCE seq_admin_activity START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_admin_notifications START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_admin_users START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_applicants START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_application_bans START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_job START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_job_applicants START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_notifications START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_user_draft_documents START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_user_education START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_user_experience START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_user_skills START WITH 1 INCREMENT BY 1;

-- ============================================================
-- TABLE: admin_activity
-- ============================================================

CREATE TABLE admin_activity (
    id              NUMBER(10)       NOT NULL,
    activity_type   VARCHAR2(100)    NOT NULL,
    description     CLOB,
    user_name       VARCHAR2(255),
    related_table   VARCHAR2(100),
    related_id      NUMBER(10),
    created_at      TIMESTAMP        DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT pk_admin_activity PRIMARY KEY (id)
);

CREATE INDEX idx_admin_activity_type     ON admin_activity (activity_type);
CREATE INDEX idx_admin_activity_created  ON admin_activity (created_at);

-- ============================================================
-- TABLE: admin_notifications
-- ============================================================

CREATE TABLE admin_notifications (
    id              NUMBER(10)       NOT NULL,
    title           VARCHAR2(255)    NOT NULL,
    message         CLOB,
    type            VARCHAR2(50)     DEFAULT 'info',
    action_type     VARCHAR2(100),
    applicant_id    NUMBER(10),
    applicant_name  VARCHAR2(255),
    is_read         NUMBER(1)        DEFAULT 0,
    created_at      TIMESTAMP        DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT pk_admin_notifications PRIMARY KEY (id)
);

CREATE INDEX idx_admin_notif_is_read ON admin_notifications (is_read);

-- ============================================================
-- TABLE: admin_users
-- ============================================================

CREATE TABLE admin_users (
    id                      NUMBER(10)       NOT NULL,
    full_name               VARCHAR2(100)    NOT NULL,
    email                   VARCHAR2(100)    NOT NULL,
    password                VARCHAR2(255)    NOT NULL,
    role                    VARCHAR2(50)     NOT NULL,
    department              VARCHAR2(100)    NOT NULL,
    profile_picture         VARCHAR2(255),
    phone                   VARCHAR2(20),
    status                  VARCHAR2(20)     DEFAULT 'Active',
    password_change_required NUMBER(1)        DEFAULT 0,
    last_login              TIMESTAMP,
    created_at              TIMESTAMP        DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at              TIMESTAMP        DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT pk_admin_users PRIMARY KEY (id),
    CONSTRAINT uk_admin_users_email UNIQUE (email),
    CONSTRAINT chk_admin_users_status CHECK (status IN ('Active', 'Inactive', 'Suspended'))
);

CREATE INDEX idx_admin_users_email      ON admin_users (email);
CREATE INDEX idx_admin_users_department ON admin_users (department);
CREATE INDEX idx_admin_users_status     ON admin_users (status);

-- ============================================================
-- TABLE: applicants
-- ============================================================

CREATE TABLE applicants (
    id                      NUMBER(10)       NOT NULL,
    first_name              VARCHAR2(100)    NOT NULL,
    last_name               VARCHAR2(100)    NOT NULL,
    applicant_email         VARCHAR2(255)    NOT NULL,
    applicant_password      VARCHAR2(255)    NOT NULL,
    contact_number          VARCHAR2(20),
    address                 CLOB,
    profile_picture         VARCHAR2(255),
    is_verified             NUMBER(1)        DEFAULT 0,
    password_change_required NUMBER(1)        DEFAULT 0,
    rejection_ban_until     TIMESTAMP,
    ban_reason              CLOB,
    banned_by               VARCHAR2(255),
    rejection_count         NUMBER(10)       DEFAULT 0,
    created_at              TIMESTAMP        DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at              TIMESTAMP        DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT pk_applicants PRIMARY KEY (id),
    CONSTRAINT uk_applicants_email UNIQUE (applicant_email)
);

CREATE INDEX idx_applicants_email ON applicants (applicant_email);

-- ============================================================
-- TABLE: application_bans
-- ============================================================

CREATE TABLE application_bans (
    id                  NUMBER(10)       NOT NULL,
    applicant_id        NUMBER(10),
    applicant_email     VARCHAR2(255),
    application_id      NUMBER(10),
    banned_date         TIMESTAMP,
    ban_expires         TIMESTAMP,
    ban_reason          CLOB,
    banned_by_id        NUMBER(10),
    banned_by_name      VARCHAR2(255),
    banned_by_role      VARCHAR2(100),
    rejection_reason    CLOB,
    position_applied    VARCHAR2(255),
    created_at          TIMESTAMP        DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT pk_application_bans PRIMARY KEY (id)
);

CREATE INDEX idx_appban_applicant_id ON application_bans (applicant_id);

-- ============================================================
-- TABLE: job
-- ============================================================

CREATE TABLE job (
    id                   NUMBER(10)       NOT NULL,
    job_title            VARCHAR2(255)    NOT NULL,
    department_role      VARCHAR2(100),
    job_type             VARCHAR2(50),
    locations            VARCHAR2(255),
    salary_range         VARCHAR2(100),
    application_deadline DATE,
    job_description      CLOB,
    job_requirements     CLOB,
    education            VARCHAR2(255),
    experience           VARCHAR2(255),
    training             VARCHAR2(255),
    eligibility          VARCHAR2(255),
    competency           VARCHAR2(255),
    status               VARCHAR2(20)     DEFAULT 'active',
    created_at           TIMESTAMP        DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at           TIMESTAMP        DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT pk_job PRIMARY KEY (id)
);

-- ============================================================
-- TABLE: job_applicants
-- ============================================================

CREATE TABLE job_applicants (
    id                       NUMBER(10)       NOT NULL,
    user_id                  NUMBER(10),
    job_id                   NUMBER(10),
    applicant_email          VARCHAR2(255),
    contact_num              VARCHAR2(20),
    full_name                VARCHAR2(255),
    position                 VARCHAR2(255),
    address                  CLOB,
    status                   VARCHAR2(50)     DEFAULT 'Pending',
    applied_date             TIMESTAMP        DEFAULT CURRENT_TIMESTAMP,
    interview_date           TIMESTAMP,
    interview_location       VARCHAR2(255),
    interview_room           VARCHAR2(255),
    interview_notes          CLOB,
    demo_date                TIMESTAMP,
    demo_location            VARCHAR2(255),
    demo_room                VARCHAR2(255),
    demo_notes               CLOB,
    psych_exam_date          TIMESTAMP,
    psych_exam_receipt       VARCHAR2(255),
    psych_exam_notes         CLOB,
    initially_hired_date     TIMESTAMP,
    initially_hired_notes    CLOB,
    hired_date               TIMESTAMP,
    hired_notes              CLOB,
    hire_notes               CLOB,
    rejection_reason         CLOB,
    rejected_date            TIMESTAMP,
    resubmission_documents   CLOB,
    resubmission_notes       CLOB,
    workflow_stage           VARCHAR2(50),
    secretary_id             NUMBER(10),
    documents_approved       NUMBER(1)        DEFAULT 0,
    assigned_to_department   VARCHAR2(100),
    application_letter       VARCHAR2(255),
    resume                   VARCHAR2(255),
    tor                      VARCHAR2(255),
    diploma                  VARCHAR2(255),
    professional_license     VARCHAR2(255),
    coe                      VARCHAR2(255),
    seminars_trainings       VARCHAR2(255),
    masteral_cert            VARCHAR2(255),
    letter_of_intent         VARCHAR2(255),
    created_at               TIMESTAMP        DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at               TIMESTAMP        DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT pk_job_applicants PRIMARY KEY (id)
);

CREATE INDEX idx_ja_user_id        ON job_applicants (user_id);
CREATE INDEX idx_ja_job_id         ON job_applicants (job_id);
CREATE INDEX idx_ja_status         ON job_applicants (status);
CREATE INDEX idx_ja_applicant_email ON job_applicants (applicant_email);

-- ============================================================
-- TABLE: notifications
-- ============================================================

CREATE TABLE notifications (
    id              NUMBER(10)       NOT NULL,
    user_email      VARCHAR2(255)    NOT NULL,
    user_name       VARCHAR2(255),
    title           VARCHAR2(255)    NOT NULL,
    message         CLOB,
    type            VARCHAR2(50)     DEFAULT 'info',
    is_read         NUMBER(1)        DEFAULT 0,
    created_at      TIMESTAMP        DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT pk_notifications PRIMARY KEY (id)
);

CREATE INDEX idx_notif_user_email ON notifications (user_email);
CREATE INDEX idx_notif_is_read    ON notifications (is_read);

-- ============================================================
-- TABLE: user_draft_documents
-- ============================================================

CREATE TABLE user_draft_documents (
    id              NUMBER(10)       NOT NULL,
    user_id         NUMBER(10)       NOT NULL,
    job_id          NUMBER(10),
    document_type   VARCHAR2(100),
    document_name   VARCHAR2(255),
    file_path       VARCHAR2(500),
    uploaded_at     TIMESTAMP        DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT pk_user_draft_documents PRIMARY KEY (id)
);

CREATE INDEX idx_udd_user_id ON user_draft_documents (user_id);

-- ============================================================
-- TABLE: user_education
-- ============================================================

CREATE TABLE user_education (
    id              NUMBER(10)       NOT NULL,
    user_id         NUMBER(10)       NOT NULL,
    degree          VARCHAR2(255),
    field_of_study  VARCHAR2(255),
    institution     VARCHAR2(255),
    start_year      NUMBER(10),
    end_year        NUMBER(10),
    gpa             VARCHAR2(20),
    created_at      TIMESTAMP        DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT pk_user_education PRIMARY KEY (id)
);

CREATE INDEX idx_ue_user_id ON user_education (user_id);

-- ============================================================
-- TABLE: user_experience
-- ============================================================

CREATE TABLE user_experience (
    id              NUMBER(10)       NOT NULL,
    user_id         NUMBER(10)       NOT NULL,
    job_title       VARCHAR2(255),
    company         VARCHAR2(255),
    location        VARCHAR2(255),
    start_date      DATE,
    end_date        DATE,
    description     CLOB,
    is_current      NUMBER(1)        DEFAULT 0,
    created_at      TIMESTAMP        DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT pk_user_experience PRIMARY KEY (id)
);

CREATE INDEX idx_uexp_user_id ON user_experience (user_id);

-- ============================================================
-- TABLE: user_skills
-- ============================================================

CREATE TABLE user_skills (
    id              NUMBER(10)       NOT NULL,
    user_id         NUMBER(10)       NOT NULL,
    skill_name      VARCHAR2(255),
    skill_category  VARCHAR2(100)    DEFAULT 'general',
    skill_level     NUMBER(10)       DEFAULT 1,
    created_at      TIMESTAMP        DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT pk_user_skills PRIMARY KEY (id)
);

CREATE INDEX idx_uskills_user_id ON user_skills (user_id);

-- ============================================================
-- FOREIGN KEYS (logical relationships for Oracle Data Modeler)
-- ============================================================

ALTER TABLE job_applicants ADD CONSTRAINT fk_ja_user FOREIGN KEY (user_id) REFERENCES applicants(id);
ALTER TABLE job_applicants ADD CONSTRAINT fk_ja_job FOREIGN KEY (job_id) REFERENCES job(id);
ALTER TABLE job_applicants ADD CONSTRAINT fk_ja_secretary FOREIGN KEY (secretary_id) REFERENCES admin_users(id);

ALTER TABLE application_bans ADD CONSTRAINT fk_ban_applicant FOREIGN KEY (applicant_id) REFERENCES applicants(id);
ALTER TABLE application_bans ADD CONSTRAINT fk_ban_application FOREIGN KEY (application_id) REFERENCES job_applicants(id);
ALTER TABLE application_bans ADD CONSTRAINT fk_ban_admin FOREIGN KEY (banned_by_id) REFERENCES admin_users(id);

ALTER TABLE user_education    ADD CONSTRAINT fk_ue_user FOREIGN KEY (user_id) REFERENCES applicants(id);
ALTER TABLE user_experience   ADD CONSTRAINT fk_uexp_user FOREIGN KEY (user_id) REFERENCES applicants(id);
ALTER TABLE user_skills       ADD CONSTRAINT fk_uskills_user FOREIGN KEY (user_id) REFERENCES applicants(id);
ALTER TABLE user_draft_documents ADD CONSTRAINT fk_udd_user FOREIGN KEY (user_id) REFERENCES applicants(id);
ALTER TABLE user_draft_documents ADD CONSTRAINT fk_udd_job FOREIGN KEY (job_id) REFERENCES job(id);

-- ============================================================
-- TRIGGERS (Oracle equivalent of MySQL ON UPDATE CURRENT_TIMESTAMP)
-- ============================================================

CREATE OR REPLACE TRIGGER trg_admin_users_updated
BEFORE UPDATE ON admin_users
FOR EACH ROW
BEGIN
    :NEW.updated_at := CURRENT_TIMESTAMP;
END;
/

CREATE OR REPLACE TRIGGER trg_applicants_updated
BEFORE UPDATE ON applicants
FOR EACH ROW
BEGIN
    :NEW.updated_at := CURRENT_TIMESTAMP;
END;
/

CREATE OR REPLACE TRIGGER trg_job_updated
BEFORE UPDATE ON job
FOR EACH ROW
BEGIN
    :NEW.updated_at := CURRENT_TIMESTAMP;
END;
/

CREATE OR REPLACE TRIGGER trg_job_applicants_updated
BEFORE UPDATE ON job_applicants
FOR EACH ROW
BEGIN
    :NEW.updated_at := CURRENT_TIMESTAMP;
END;
/
