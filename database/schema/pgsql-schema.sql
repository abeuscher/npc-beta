--
-- PostgreSQL database dump
--

\restrict Wl10mQsdBvwkfkk6jiuT8EnlJ0CcTp4dF36jJIl8gsfUsp65cQ1VcfkKFfl8KGg

-- Dumped from database version 16.13
-- Dumped by pg_dump version 17.9 (Debian 17.9-0+deb13u1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: activity_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.activity_logs (
    id bigint NOT NULL,
    subject_type character varying(255) NOT NULL,
    subject_id character varying(255) NOT NULL,
    actor_type character varying(255) NOT NULL,
    actor_id bigint,
    event character varying(255) NOT NULL,
    description character varying(255),
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    meta json
);


--
-- Name: activity_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.activity_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: activity_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.activity_logs_id_seq OWNED BY public.activity_logs.id;


--
-- Name: allocations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.allocations (
    id uuid NOT NULL,
    product_id uuid NOT NULL,
    product_price_id uuid NOT NULL,
    contact_id uuid,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    occurred_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: campaigns; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.campaigns (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    goal_amount numeric(10,2),
    starts_on date,
    ends_on date,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: collection_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.collection_items (
    id uuid NOT NULL,
    collection_id uuid NOT NULL,
    data jsonb DEFAULT '{}'::jsonb NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    is_published boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: collections; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.collections (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    handle character varying(255) NOT NULL,
    description text,
    fields jsonb DEFAULT '[]'::jsonb NOT NULL,
    source_type character varying(255) DEFAULT 'custom'::character varying NOT NULL,
    is_public boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: contact_duplicate_dismissals; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.contact_duplicate_dismissals (
    id uuid NOT NULL,
    contact_id_a uuid NOT NULL,
    contact_id_b uuid NOT NULL,
    dismissed_by bigint,
    dismissed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: contacts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.contacts (
    id uuid NOT NULL,
    prefix character varying(255),
    first_name character varying(255),
    last_name character varying(255),
    email character varying(255),
    phone character varying(255),
    address_line_1 character varying(255),
    address_line_2 character varying(255),
    city character varying(255),
    state character varying(255),
    postal_code character varying(255),
    country character varying(255) DEFAULT 'US'::character varying,
    custom_data jsonb,
    do_not_contact boolean DEFAULT false NOT NULL,
    source character varying(255) DEFAULT 'manual'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    organization_id uuid,
    household_id uuid,
    custom_fields jsonb,
    mailing_list_opt_in boolean DEFAULT false NOT NULL,
    import_session_id uuid,
    date_of_birth date,
    quickbooks_customer_id character varying(255)
);


--
-- Name: custom_field_defs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.custom_field_defs (
    id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    handle character varying(255) NOT NULL,
    label character varying(255) NOT NULL,
    field_type character varying(255) DEFAULT 'text'::character varying NOT NULL,
    options jsonb,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_filterable boolean DEFAULT false NOT NULL
);


--
-- Name: custom_field_defs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.custom_field_defs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: custom_field_defs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.custom_field_defs_id_seq OWNED BY public.custom_field_defs.id;


--
-- Name: donation_receipts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.donation_receipts (
    id bigint NOT NULL,
    contact_id uuid NOT NULL,
    tax_year integer NOT NULL,
    sent_at timestamp(0) without time zone NOT NULL,
    total_amount numeric(10,2) NOT NULL,
    breakdown json NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: donation_receipts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.donation_receipts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: donation_receipts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.donation_receipts_id_seq OWNED BY public.donation_receipts.id;


--
-- Name: donations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.donations (
    id uuid NOT NULL,
    contact_id uuid,
    amount numeric(10,2) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    type character varying(255) NOT NULL,
    currency character varying(3) DEFAULT 'usd'::character varying NOT NULL,
    frequency character varying(255),
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    stripe_subscription_id character varying(255),
    stripe_customer_id character varying(255),
    started_at timestamp(0) without time zone,
    ended_at timestamp(0) without time zone,
    fund_id uuid
);


--
-- Name: email_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.email_templates (
    id bigint NOT NULL,
    handle character varying(255) NOT NULL,
    subject character varying(255) NOT NULL,
    body text NOT NULL,
    header_color character varying(255),
    header_image_path character varying(255),
    header_text character varying(255),
    footer_sender_name character varying(255),
    footer_reply_to character varying(255),
    footer_address text,
    footer_reason character varying(255),
    custom_template_path character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: email_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.email_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: email_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.email_templates_id_seq OWNED BY public.email_templates.id;


--
-- Name: event_registrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.event_registrations (
    id uuid NOT NULL,
    contact_id uuid,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    phone character varying(50),
    company character varying(255),
    address_line_1 character varying(255),
    address_line_2 character varying(255),
    city character varying(100),
    state character varying(100),
    zip character varying(20),
    status character varying(255) DEFAULT 'registered'::character varying NOT NULL,
    registered_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    stripe_payment_intent_id character varying(255),
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    event_id uuid NOT NULL,
    mailing_list_opt_in boolean DEFAULT false NOT NULL,
    stripe_session_id character varying(255),
    CONSTRAINT event_registrations_status_check CHECK (((status)::text = ANY (ARRAY['pending'::text, 'registered'::text, 'waitlisted'::text, 'cancelled'::text, 'attended'::text])))
);


--
-- Name: events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.events (
    id uuid NOT NULL,
    title character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    description text,
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    is_in_person boolean DEFAULT true NOT NULL,
    address_line_1 character varying(255),
    address_line_2 character varying(255),
    city character varying(100),
    state character varying(100),
    zip character varying(20),
    map_url character varying(2048),
    map_label character varying(255),
    is_virtual boolean DEFAULT false NOT NULL,
    meeting_url character varying(2048),
    is_free boolean DEFAULT true NOT NULL,
    capacity integer,
    is_recurring boolean DEFAULT false NOT NULL,
    recurrence_type character varying(255),
    recurrence_rule json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    landing_page_id uuid,
    meeting_label character varying(255),
    meeting_details text,
    price numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    external_registration_url character varying(255),
    registration_mode character varying(255) DEFAULT 'open'::character varying NOT NULL,
    auto_create_contacts boolean DEFAULT true NOT NULL,
    mailing_list_opt_in_enabled boolean DEFAULT false NOT NULL,
    custom_fields jsonb,
    starts_at timestamp(0) without time zone NOT NULL,
    ends_at timestamp(0) without time zone,
    registrants_deleted_at timestamp(0) without time zone,
    author_id bigint NOT NULL,
    CONSTRAINT events_recurrence_type_check CHECK (((recurrence_type)::text = ANY (ARRAY[('manual'::character varying)::text, ('rule'::character varying)::text]))),
    CONSTRAINT events_status_check CHECK (((status)::text = ANY (ARRAY[('draft'::character varying)::text, ('published'::character varying)::text, ('cancelled'::character varying)::text])))
);


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: form_submissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.form_submissions (
    id bigint NOT NULL,
    form_id bigint NOT NULL,
    data json DEFAULT '{}'::json NOT NULL,
    ip_address character varying(255),
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    contact_id uuid,
    deleted_at timestamp(0) without time zone
);


--
-- Name: form_submissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.form_submissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: form_submissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.form_submissions_id_seq OWNED BY public.form_submissions.id;


--
-- Name: forms; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.forms (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    handle character varying(255) NOT NULL,
    description text,
    fields json DEFAULT '[]'::json NOT NULL,
    settings json DEFAULT '{}'::json NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    is_archived boolean DEFAULT false NOT NULL
);


--
-- Name: forms_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.forms_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: forms_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.forms_id_seq OWNED BY public.forms.id;


--
-- Name: funds; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.funds (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    code character varying(255) NOT NULL,
    description text,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    restriction_type character varying(255) DEFAULT 'unrestricted'::character varying NOT NULL,
    quickbooks_account_id character varying(255),
    is_archived boolean DEFAULT false NOT NULL
);


--
-- Name: help_article_routes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.help_article_routes (
    id bigint NOT NULL,
    help_article_id bigint NOT NULL,
    route_name character varying(255) NOT NULL
);


--
-- Name: help_article_routes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.help_article_routes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: help_article_routes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.help_article_routes_id_seq OWNED BY public.help_article_routes.id;


--
-- Name: help_articles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.help_articles (
    id bigint NOT NULL,
    slug character varying(255) NOT NULL,
    title character varying(255) NOT NULL,
    description text NOT NULL,
    content text NOT NULL,
    tags json,
    app_version character varying(255),
    last_updated date,
    embedding jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    category character varying(255)
);


--
-- Name: help_articles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.help_articles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: help_articles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.help_articles_id_seq OWNED BY public.help_articles.id;


--
-- Name: import_id_maps; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.import_id_maps (
    id uuid NOT NULL,
    import_source_id uuid NOT NULL,
    model_type character varying(255) NOT NULL,
    source_id character varying(255) NOT NULL,
    model_uuid uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: import_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.import_logs (
    id uuid NOT NULL,
    user_id bigint,
    model_type character varying(255) NOT NULL,
    filename character varying(255) NOT NULL,
    row_count integer DEFAULT 0 NOT NULL,
    imported_count integer DEFAULT 0 NOT NULL,
    updated_count integer DEFAULT 0 NOT NULL,
    skipped_count integer DEFAULT 0 NOT NULL,
    error_count integer DEFAULT 0 NOT NULL,
    errors jsonb,
    duplicate_strategy character varying(255) DEFAULT 'skip'::character varying NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    started_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    storage_path character varying(255),
    column_map jsonb,
    custom_field_map jsonb,
    custom_field_log jsonb
);


--
-- Name: import_sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.import_sessions (
    id uuid NOT NULL,
    import_source_id uuid,
    model_type character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    filename character varying(255),
    row_count integer,
    imported_by bigint,
    approved_by bigint,
    approved_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    tag_ids jsonb,
    session_label character varying(255)
);


--
-- Name: import_sources; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.import_sources (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: import_staged_updates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.import_staged_updates (
    id bigint NOT NULL,
    import_session_id uuid NOT NULL,
    contact_id uuid NOT NULL,
    attributes jsonb,
    tag_ids jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: import_staged_updates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.import_staged_updates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: import_staged_updates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.import_staged_updates_id_seq OWNED BY public.import_staged_updates.id;


--
-- Name: invitation_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.invitation_tokens (
    id uuid NOT NULL,
    user_id bigint NOT NULL,
    token character varying(255) NOT NULL,
    expires_at timestamp(0) without time zone NOT NULL,
    accepted_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: mailing_list_filters; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mailing_list_filters (
    id uuid NOT NULL,
    mailing_list_id uuid NOT NULL,
    field character varying(255) NOT NULL,
    operator character varying(255) NOT NULL,
    value character varying(255),
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: mailing_lists; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mailing_lists (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    conjunction character varying(255) DEFAULT 'and'::character varying NOT NULL,
    raw_where text,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: media; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.media (
    id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id character varying(36) NOT NULL,
    uuid uuid,
    collection_name character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    file_name character varying(255) NOT NULL,
    mime_type character varying(255),
    disk character varying(255) NOT NULL,
    conversions_disk character varying(255),
    size bigint NOT NULL,
    manipulations json NOT NULL,
    custom_properties json NOT NULL,
    generated_conversions json NOT NULL,
    responsive_images json NOT NULL,
    order_column integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: media_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.media_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: media_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.media_id_seq OWNED BY public.media.id;


--
-- Name: membership_tiers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.membership_tiers (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    billing_interval character varying(255) NOT NULL,
    default_price numeric(8,2),
    renewal_notice_days integer DEFAULT 30 NOT NULL,
    description text,
    is_active boolean DEFAULT true NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_archived boolean DEFAULT false NOT NULL,
    CONSTRAINT membership_tiers_billing_interval_check CHECK (((billing_interval)::text = ANY (ARRAY[('monthly'::character varying)::text, ('annual'::character varying)::text, ('one_time'::character varying)::text, ('lifetime'::character varying)::text])))
);


--
-- Name: memberships; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.memberships (
    id uuid NOT NULL,
    contact_id uuid NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    starts_on date,
    expires_on date,
    amount_paid numeric(10,2),
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    tier_id uuid,
    stripe_session_id character varying(255),
    stripe_subscription_id character varying(255)
);


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: model_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_permissions (
    permission_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


--
-- Name: model_has_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_roles (
    role_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


--
-- Name: navigation_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.navigation_items (
    id uuid NOT NULL,
    label character varying(255) NOT NULL,
    url character varying(255),
    page_id uuid,
    parent_id uuid,
    sort_order integer DEFAULT 0 NOT NULL,
    target character varying(255) DEFAULT '_self'::character varying NOT NULL,
    is_visible boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    navigation_menu_id uuid NOT NULL
);


--
-- Name: navigation_menus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.navigation_menus (
    id uuid NOT NULL,
    label character varying(255) NOT NULL,
    handle character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: notes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notes (
    id uuid NOT NULL,
    notable_type character varying(255) NOT NULL,
    notable_id uuid NOT NULL,
    author_id bigint,
    body text NOT NULL,
    occurred_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: organizations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.organizations (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    type character varying(255),
    website character varying(255),
    phone character varying(255),
    address_line_1 character varying(255),
    address_line_2 character varying(255),
    city character varying(255),
    state character varying(255),
    postal_code character varying(255),
    country character varying(255) DEFAULT 'US'::character varying,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: page_layouts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.page_layouts (
    id uuid NOT NULL,
    page_id uuid NOT NULL,
    label character varying(255),
    display character varying(255) DEFAULT 'grid'::character varying NOT NULL,
    columns integer DEFAULT 2 NOT NULL,
    layout_config jsonb DEFAULT '{}'::jsonb NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: page_widgets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.page_widgets (
    id uuid NOT NULL,
    page_id uuid NOT NULL,
    label character varying(255),
    config jsonb DEFAULT '{}'::jsonb NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    widget_type_id uuid NOT NULL,
    query_config jsonb DEFAULT '{}'::jsonb NOT NULL,
    column_index smallint,
    appearance_config jsonb DEFAULT '{}'::jsonb NOT NULL,
    layout_id uuid
);


--
-- Name: pages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pages (
    id uuid NOT NULL,
    title character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    meta_title character varying(255),
    meta_description text,
    published_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    type character varying(255) DEFAULT 'default'::character varying NOT NULL,
    custom_fields jsonb,
    author_id bigint NOT NULL,
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    noindex boolean DEFAULT false NOT NULL,
    head_snippet text,
    body_snippet text,
    template_id uuid,
    CONSTRAINT pages_type_check CHECK (((type)::text = ANY (ARRAY[('default'::character varying)::text, ('post'::character varying)::text, ('event'::character varying)::text, ('member'::character varying)::text, ('system'::character varying)::text])))
);


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissions (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: portal_accounts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.portal_accounts (
    id bigint NOT NULL,
    contact_id uuid,
    email character varying(255) NOT NULL,
    password character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_active boolean DEFAULT true NOT NULL
);


--
-- Name: portal_accounts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.portal_accounts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: portal_accounts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.portal_accounts_id_seq OWNED BY public.portal_accounts.id;


--
-- Name: portal_password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.portal_password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: posts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.posts (
    id uuid NOT NULL,
    title character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    excerpt text,
    content text,
    author_id bigint,
    is_published boolean DEFAULT false NOT NULL,
    published_at timestamp(0) without time zone,
    meta_title character varying(255),
    meta_description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: product_prices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_prices (
    id uuid NOT NULL,
    product_id uuid NOT NULL,
    label character varying(255) NOT NULL,
    amount numeric(10,2) NOT NULL,
    stripe_price_id character varying(255),
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: products; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.products (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    description text,
    capacity integer NOT NULL,
    stripe_product_id character varying(255),
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_archived boolean DEFAULT false NOT NULL
);


--
-- Name: purchases; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchases (
    id uuid NOT NULL,
    product_id uuid NOT NULL,
    product_price_id uuid NOT NULL,
    contact_id uuid,
    stripe_session_id character varying(255),
    amount_paid numeric(10,2) NOT NULL,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    occurred_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: role_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.role_has_permissions (
    permission_id bigint NOT NULL,
    role_id bigint NOT NULL
);


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    label character varying(255)
);


--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: site_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.site_settings (
    id bigint NOT NULL,
    key character varying(255) NOT NULL,
    value text,
    "group" character varying(255) DEFAULT 'general'::character varying NOT NULL,
    type character varying(255) DEFAULT 'string'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: site_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.site_settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: site_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.site_settings_id_seq OWNED BY public.site_settings.id;


--
-- Name: taggables; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.taggables (
    tag_id uuid NOT NULL,
    taggable_type character varying(255) NOT NULL,
    taggable_id uuid NOT NULL
);


--
-- Name: tags; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tags (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    type character varying(255) DEFAULT 'contact'::character varying NOT NULL,
    slug character varying(255) NOT NULL
);


--
-- Name: templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.templates (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    type character varying(255) NOT NULL,
    description text,
    is_default boolean DEFAULT false NOT NULL,
    definition jsonb DEFAULT '{}'::jsonb NOT NULL,
    primary_color character varying(255),
    heading_font character varying(255),
    body_font character varying(255),
    header_bg_color character varying(255),
    footer_bg_color character varying(255),
    nav_link_color character varying(255),
    nav_hover_color character varying(255),
    nav_active_color character varying(255),
    custom_scss text,
    header_page_id uuid,
    footer_page_id uuid,
    created_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: transactions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.transactions (
    id uuid NOT NULL,
    type character varying(255) DEFAULT 'donation'::character varying NOT NULL,
    amount numeric(10,2) NOT NULL,
    direction character varying(255) DEFAULT 'in'::character varying NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    stripe_id character varying(255),
    quickbooks_id character varying(255),
    occurred_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    subject_type character varying(255),
    subject_id character varying(255),
    contact_id uuid,
    qb_sync_error text,
    qb_synced_at timestamp(0) without time zone
);


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_active boolean DEFAULT true NOT NULL
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: waitlist_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.waitlist_entries (
    id uuid NOT NULL,
    product_id uuid NOT NULL,
    contact_id uuid,
    status character varying(255) DEFAULT 'waiting'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: widget_presets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.widget_presets (
    id uuid NOT NULL,
    widget_type_id uuid NOT NULL,
    handle character varying(255) NOT NULL,
    label character varying(255) NOT NULL,
    description text,
    config jsonb DEFAULT '{}'::jsonb NOT NULL,
    appearance_config jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: widget_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.widget_types (
    id uuid NOT NULL,
    handle character varying(255) NOT NULL,
    label character varying(255) NOT NULL,
    render_mode character varying(255) DEFAULT 'server'::character varying NOT NULL,
    collections jsonb DEFAULT '[]'::jsonb NOT NULL,
    template text,
    css text,
    js text,
    variable_name character varying(255),
    code text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    config_schema jsonb DEFAULT '[]'::jsonb NOT NULL,
    default_open boolean DEFAULT false NOT NULL,
    assets jsonb DEFAULT '{}'::jsonb NOT NULL,
    category jsonb DEFAULT '["content"]'::jsonb NOT NULL,
    allowed_page_types jsonb,
    description text,
    full_width boolean DEFAULT false NOT NULL,
    required_config jsonb,
    CONSTRAINT widget_types_render_mode_check CHECK (((render_mode)::text = ANY (ARRAY[('server'::character varying)::text, ('client'::character varying)::text])))
);


--
-- Name: activity_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_logs ALTER COLUMN id SET DEFAULT nextval('public.activity_logs_id_seq'::regclass);


--
-- Name: custom_field_defs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.custom_field_defs ALTER COLUMN id SET DEFAULT nextval('public.custom_field_defs_id_seq'::regclass);


--
-- Name: donation_receipts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.donation_receipts ALTER COLUMN id SET DEFAULT nextval('public.donation_receipts_id_seq'::regclass);


--
-- Name: email_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_templates ALTER COLUMN id SET DEFAULT nextval('public.email_templates_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: form_submissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.form_submissions ALTER COLUMN id SET DEFAULT nextval('public.form_submissions_id_seq'::regclass);


--
-- Name: forms id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.forms ALTER COLUMN id SET DEFAULT nextval('public.forms_id_seq'::regclass);


--
-- Name: help_article_routes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.help_article_routes ALTER COLUMN id SET DEFAULT nextval('public.help_article_routes_id_seq'::regclass);


--
-- Name: help_articles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.help_articles ALTER COLUMN id SET DEFAULT nextval('public.help_articles_id_seq'::regclass);


--
-- Name: import_staged_updates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_staged_updates ALTER COLUMN id SET DEFAULT nextval('public.import_staged_updates_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: media id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media ALTER COLUMN id SET DEFAULT nextval('public.media_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: portal_accounts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_accounts ALTER COLUMN id SET DEFAULT nextval('public.portal_accounts_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: site_settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.site_settings ALTER COLUMN id SET DEFAULT nextval('public.site_settings_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: activity_logs activity_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_logs
    ADD CONSTRAINT activity_logs_pkey PRIMARY KEY (id);


--
-- Name: allocations allocations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.allocations
    ADD CONSTRAINT allocations_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: campaigns campaigns_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.campaigns
    ADD CONSTRAINT campaigns_pkey PRIMARY KEY (id);


--
-- Name: collection_items collection_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.collection_items
    ADD CONSTRAINT collection_items_pkey PRIMARY KEY (id);


--
-- Name: collections collections_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.collections
    ADD CONSTRAINT collections_pkey PRIMARY KEY (id);


--
-- Name: collections collections_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.collections
    ADD CONSTRAINT collections_slug_unique UNIQUE (handle);


--
-- Name: contact_duplicate_dismissals contact_duplicate_dismissals_contact_id_a_contact_id_b_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contact_duplicate_dismissals
    ADD CONSTRAINT contact_duplicate_dismissals_contact_id_a_contact_id_b_unique UNIQUE (contact_id_a, contact_id_b);


--
-- Name: contact_duplicate_dismissals contact_duplicate_dismissals_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contact_duplicate_dismissals
    ADD CONSTRAINT contact_duplicate_dismissals_pkey PRIMARY KEY (id);


--
-- Name: contacts contacts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contacts
    ADD CONSTRAINT contacts_pkey PRIMARY KEY (id);


--
-- Name: custom_field_defs custom_field_defs_model_type_handle_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.custom_field_defs
    ADD CONSTRAINT custom_field_defs_model_type_handle_unique UNIQUE (model_type, handle);


--
-- Name: custom_field_defs custom_field_defs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.custom_field_defs
    ADD CONSTRAINT custom_field_defs_pkey PRIMARY KEY (id);


--
-- Name: donation_receipts donation_receipts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.donation_receipts
    ADD CONSTRAINT donation_receipts_pkey PRIMARY KEY (id);


--
-- Name: donations donations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.donations
    ADD CONSTRAINT donations_pkey PRIMARY KEY (id);


--
-- Name: email_templates email_templates_handle_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_templates
    ADD CONSTRAINT email_templates_handle_unique UNIQUE (handle);


--
-- Name: email_templates email_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_templates
    ADD CONSTRAINT email_templates_pkey PRIMARY KEY (id);


--
-- Name: event_registrations event_registrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_registrations
    ADD CONSTRAINT event_registrations_pkey PRIMARY KEY (id);


--
-- Name: events events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_pkey PRIMARY KEY (id);


--
-- Name: events events_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_slug_unique UNIQUE (slug);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: form_submissions form_submissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.form_submissions
    ADD CONSTRAINT form_submissions_pkey PRIMARY KEY (id);


--
-- Name: forms forms_handle_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.forms
    ADD CONSTRAINT forms_handle_unique UNIQUE (handle);


--
-- Name: forms forms_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.forms
    ADD CONSTRAINT forms_pkey PRIMARY KEY (id);


--
-- Name: funds funds_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.funds
    ADD CONSTRAINT funds_code_unique UNIQUE (code);


--
-- Name: funds funds_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.funds
    ADD CONSTRAINT funds_pkey PRIMARY KEY (id);


--
-- Name: help_article_routes help_article_routes_help_article_id_route_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.help_article_routes
    ADD CONSTRAINT help_article_routes_help_article_id_route_name_unique UNIQUE (help_article_id, route_name);


--
-- Name: help_article_routes help_article_routes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.help_article_routes
    ADD CONSTRAINT help_article_routes_pkey PRIMARY KEY (id);


--
-- Name: help_articles help_articles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.help_articles
    ADD CONSTRAINT help_articles_pkey PRIMARY KEY (id);


--
-- Name: help_articles help_articles_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.help_articles
    ADD CONSTRAINT help_articles_slug_unique UNIQUE (slug);


--
-- Name: import_id_maps import_id_maps_import_source_id_model_type_source_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_id_maps
    ADD CONSTRAINT import_id_maps_import_source_id_model_type_source_id_unique UNIQUE (import_source_id, model_type, source_id);


--
-- Name: import_id_maps import_id_maps_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_id_maps
    ADD CONSTRAINT import_id_maps_pkey PRIMARY KEY (id);


--
-- Name: import_logs import_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_logs
    ADD CONSTRAINT import_logs_pkey PRIMARY KEY (id);


--
-- Name: import_sessions import_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_sessions
    ADD CONSTRAINT import_sessions_pkey PRIMARY KEY (id);


--
-- Name: import_sources import_sources_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_sources
    ADD CONSTRAINT import_sources_pkey PRIMARY KEY (id);


--
-- Name: import_staged_updates import_staged_updates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_staged_updates
    ADD CONSTRAINT import_staged_updates_pkey PRIMARY KEY (id);


--
-- Name: invitation_tokens invitation_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitation_tokens
    ADD CONSTRAINT invitation_tokens_pkey PRIMARY KEY (id);


--
-- Name: invitation_tokens invitation_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitation_tokens
    ADD CONSTRAINT invitation_tokens_token_unique UNIQUE (token);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: mailing_list_filters mailing_list_filters_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mailing_list_filters
    ADD CONSTRAINT mailing_list_filters_pkey PRIMARY KEY (id);


--
-- Name: mailing_lists mailing_lists_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mailing_lists
    ADD CONSTRAINT mailing_lists_pkey PRIMARY KEY (id);


--
-- Name: media media_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media
    ADD CONSTRAINT media_pkey PRIMARY KEY (id);


--
-- Name: media media_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media
    ADD CONSTRAINT media_uuid_unique UNIQUE (uuid);


--
-- Name: membership_tiers membership_tiers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.membership_tiers
    ADD CONSTRAINT membership_tiers_pkey PRIMARY KEY (id);


--
-- Name: membership_tiers membership_tiers_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.membership_tiers
    ADD CONSTRAINT membership_tiers_slug_unique UNIQUE (slug);


--
-- Name: memberships memberships_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.memberships
    ADD CONSTRAINT memberships_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: model_has_permissions model_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_pkey PRIMARY KEY (permission_id, model_id, model_type);


--
-- Name: model_has_roles model_has_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_pkey PRIMARY KEY (role_id, model_id, model_type);


--
-- Name: navigation_items navigation_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.navigation_items
    ADD CONSTRAINT navigation_items_pkey PRIMARY KEY (id);


--
-- Name: navigation_menus navigation_menus_handle_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.navigation_menus
    ADD CONSTRAINT navigation_menus_handle_unique UNIQUE (handle);


--
-- Name: navigation_menus navigation_menus_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.navigation_menus
    ADD CONSTRAINT navigation_menus_pkey PRIMARY KEY (id);


--
-- Name: notes notes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notes
    ADD CONSTRAINT notes_pkey PRIMARY KEY (id);


--
-- Name: organizations organizations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organizations
    ADD CONSTRAINT organizations_pkey PRIMARY KEY (id);


--
-- Name: page_layouts page_layouts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.page_layouts
    ADD CONSTRAINT page_layouts_pkey PRIMARY KEY (id);


--
-- Name: page_widgets page_widgets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.page_widgets
    ADD CONSTRAINT page_widgets_pkey PRIMARY KEY (id);


--
-- Name: pages pages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pages
    ADD CONSTRAINT pages_pkey PRIMARY KEY (id);


--
-- Name: pages pages_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pages
    ADD CONSTRAINT pages_slug_unique UNIQUE (slug);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: permissions permissions_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: portal_accounts portal_accounts_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_accounts
    ADD CONSTRAINT portal_accounts_email_unique UNIQUE (email);


--
-- Name: portal_accounts portal_accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_accounts
    ADD CONSTRAINT portal_accounts_pkey PRIMARY KEY (id);


--
-- Name: portal_password_reset_tokens portal_password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_password_reset_tokens
    ADD CONSTRAINT portal_password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: posts posts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posts
    ADD CONSTRAINT posts_pkey PRIMARY KEY (id);


--
-- Name: posts posts_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posts
    ADD CONSTRAINT posts_slug_unique UNIQUE (slug);


--
-- Name: product_prices product_prices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_prices
    ADD CONSTRAINT product_prices_pkey PRIMARY KEY (id);


--
-- Name: products products_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_pkey PRIMARY KEY (id);


--
-- Name: products products_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_slug_unique UNIQUE (slug);


--
-- Name: purchases purchases_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchases
    ADD CONSTRAINT purchases_pkey PRIMARY KEY (id);


--
-- Name: role_has_permissions role_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_pkey PRIMARY KEY (permission_id, role_id);


--
-- Name: roles roles_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: site_settings site_settings_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.site_settings
    ADD CONSTRAINT site_settings_key_unique UNIQUE (key);


--
-- Name: site_settings site_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.site_settings
    ADD CONSTRAINT site_settings_pkey PRIMARY KEY (id);


--
-- Name: taggables taggables_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.taggables
    ADD CONSTRAINT taggables_pkey PRIMARY KEY (tag_id, taggable_id, taggable_type);


--
-- Name: tags tags_name_type_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tags
    ADD CONSTRAINT tags_name_type_unique UNIQUE (name, type);


--
-- Name: tags tags_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tags
    ADD CONSTRAINT tags_pkey PRIMARY KEY (id);


--
-- Name: tags tags_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tags
    ADD CONSTRAINT tags_slug_unique UNIQUE (slug);


--
-- Name: templates templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.templates
    ADD CONSTRAINT templates_pkey PRIMARY KEY (id);


--
-- Name: transactions transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transactions
    ADD CONSTRAINT transactions_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: waitlist_entries waitlist_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.waitlist_entries
    ADD CONSTRAINT waitlist_entries_pkey PRIMARY KEY (id);


--
-- Name: widget_presets widget_presets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.widget_presets
    ADD CONSTRAINT widget_presets_pkey PRIMARY KEY (id);


--
-- Name: widget_presets widget_presets_widget_type_id_handle_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.widget_presets
    ADD CONSTRAINT widget_presets_widget_type_id_handle_unique UNIQUE (widget_type_id, handle);


--
-- Name: widget_types widget_types_handle_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.widget_types
    ADD CONSTRAINT widget_types_handle_unique UNIQUE (handle);


--
-- Name: widget_types widget_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.widget_types
    ADD CONSTRAINT widget_types_pkey PRIMARY KEY (id);


--
-- Name: activity_logs_actor_type_actor_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_logs_actor_type_actor_id_index ON public.activity_logs USING btree (actor_type, actor_id);


--
-- Name: activity_logs_subject_type_subject_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_logs_subject_type_subject_id_index ON public.activity_logs USING btree (subject_type, subject_id);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: contacts_email_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX contacts_email_index ON public.contacts USING btree (email);


--
-- Name: contacts_household_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX contacts_household_id_index ON public.contacts USING btree (household_id);


--
-- Name: contacts_import_session_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX contacts_import_session_id_index ON public.contacts USING btree (import_session_id);


--
-- Name: contacts_organization_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX contacts_organization_id_index ON public.contacts USING btree (organization_id);


--
-- Name: donation_receipts_contact_id_tax_year_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX donation_receipts_contact_id_tax_year_index ON public.donation_receipts USING btree (contact_id, tax_year);


--
-- Name: donations_contact_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX donations_contact_id_index ON public.donations USING btree (contact_id);


--
-- Name: donations_fund_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX donations_fund_id_index ON public.donations USING btree (fund_id);


--
-- Name: events_landing_page_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_landing_page_id_index ON public.events USING btree (landing_page_id);


--
-- Name: form_submissions_contact_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX form_submissions_contact_id_index ON public.form_submissions USING btree (contact_id);


--
-- Name: form_submissions_form_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX form_submissions_form_id_index ON public.form_submissions USING btree (form_id);


--
-- Name: help_article_routes_help_article_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX help_article_routes_help_article_id_index ON public.help_article_routes USING btree (help_article_id);


--
-- Name: help_article_routes_route_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX help_article_routes_route_name_index ON public.help_article_routes USING btree (route_name);


--
-- Name: import_sessions_approved_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_sessions_approved_by_index ON public.import_sessions USING btree (approved_by);


--
-- Name: import_sessions_import_source_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_sessions_import_source_id_index ON public.import_sessions USING btree (import_source_id);


--
-- Name: import_sessions_imported_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_sessions_imported_by_index ON public.import_sessions USING btree (imported_by);


--
-- Name: import_staged_updates_contact_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_staged_updates_contact_id_index ON public.import_staged_updates USING btree (contact_id);


--
-- Name: import_staged_updates_import_session_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_staged_updates_import_session_id_index ON public.import_staged_updates USING btree (import_session_id);


--
-- Name: invitation_tokens_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX invitation_tokens_user_id_index ON public.invitation_tokens USING btree (user_id);


--
-- Name: jobs_queue_reserved_at_available_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_reserved_at_available_at_index ON public.jobs USING btree (queue, reserved_at, available_at);


--
-- Name: mailing_list_filters_mailing_list_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mailing_list_filters_mailing_list_id_index ON public.mailing_list_filters USING btree (mailing_list_id);


--
-- Name: media_model_type_model_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX media_model_type_model_id_index ON public.media USING btree (model_type, model_id);


--
-- Name: media_order_column_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX media_order_column_index ON public.media USING btree (order_column);


--
-- Name: memberships_contact_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX memberships_contact_id_index ON public.memberships USING btree (contact_id);


--
-- Name: model_has_permissions_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_permissions_model_id_model_type_index ON public.model_has_permissions USING btree (model_id, model_type);


--
-- Name: model_has_roles_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_roles_model_id_model_type_index ON public.model_has_roles USING btree (model_id, model_type);


--
-- Name: navigation_items_navigation_menu_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX navigation_items_navigation_menu_id_index ON public.navigation_items USING btree (navigation_menu_id);


--
-- Name: navigation_items_page_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX navigation_items_page_id_index ON public.navigation_items USING btree (page_id);


--
-- Name: navigation_items_parent_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX navigation_items_parent_id_index ON public.navigation_items USING btree (parent_id);


--
-- Name: notes_author_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notes_author_id_index ON public.notes USING btree (author_id);


--
-- Name: notes_notable_type_notable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notes_notable_type_notable_id_index ON public.notes USING btree (notable_type, notable_id);


--
-- Name: portal_accounts_contact_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX portal_accounts_contact_id_index ON public.portal_accounts USING btree (contact_id);


--
-- Name: posts_author_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX posts_author_id_index ON public.posts USING btree (author_id);


--
-- Name: product_prices_product_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX product_prices_product_id_index ON public.product_prices USING btree (product_id);


--
-- Name: purchases_contact_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchases_contact_id_index ON public.purchases USING btree (contact_id);


--
-- Name: purchases_product_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchases_product_id_index ON public.purchases USING btree (product_id);


--
-- Name: purchases_product_price_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchases_product_price_id_index ON public.purchases USING btree (product_price_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: taggables_taggable_type_taggable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX taggables_taggable_type_taggable_id_index ON public.taggables USING btree (taggable_type, taggable_id);


--
-- Name: transactions_contact_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX transactions_contact_id_index ON public.transactions USING btree (contact_id);


--
-- Name: transactions_subject_type_subject_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX transactions_subject_type_subject_id_index ON public.transactions USING btree (subject_type, subject_id);


--
-- Name: waitlist_entries_contact_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX waitlist_entries_contact_id_index ON public.waitlist_entries USING btree (contact_id);


--
-- Name: waitlist_entries_product_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX waitlist_entries_product_id_index ON public.waitlist_entries USING btree (product_id);


--
-- Name: allocations allocations_contact_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.allocations
    ADD CONSTRAINT allocations_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id) ON DELETE SET NULL;


--
-- Name: allocations allocations_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.allocations
    ADD CONSTRAINT allocations_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE RESTRICT;


--
-- Name: allocations allocations_product_price_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.allocations
    ADD CONSTRAINT allocations_product_price_id_foreign FOREIGN KEY (product_price_id) REFERENCES public.product_prices(id) ON DELETE RESTRICT;


--
-- Name: collection_items collection_items_collection_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.collection_items
    ADD CONSTRAINT collection_items_collection_id_foreign FOREIGN KEY (collection_id) REFERENCES public.collections(id) ON DELETE CASCADE;


--
-- Name: contact_duplicate_dismissals contact_duplicate_dismissals_contact_id_a_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contact_duplicate_dismissals
    ADD CONSTRAINT contact_duplicate_dismissals_contact_id_a_foreign FOREIGN KEY (contact_id_a) REFERENCES public.contacts(id) ON DELETE CASCADE;


--
-- Name: contact_duplicate_dismissals contact_duplicate_dismissals_contact_id_b_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contact_duplicate_dismissals
    ADD CONSTRAINT contact_duplicate_dismissals_contact_id_b_foreign FOREIGN KEY (contact_id_b) REFERENCES public.contacts(id) ON DELETE CASCADE;


--
-- Name: contact_duplicate_dismissals contact_duplicate_dismissals_dismissed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contact_duplicate_dismissals
    ADD CONSTRAINT contact_duplicate_dismissals_dismissed_by_foreign FOREIGN KEY (dismissed_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: contacts contacts_household_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contacts
    ADD CONSTRAINT contacts_household_id_foreign FOREIGN KEY (household_id) REFERENCES public.contacts(id) ON DELETE SET NULL;


--
-- Name: contacts contacts_import_session_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contacts
    ADD CONSTRAINT contacts_import_session_id_foreign FOREIGN KEY (import_session_id) REFERENCES public.import_sessions(id) ON DELETE SET NULL;


--
-- Name: contacts contacts_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contacts
    ADD CONSTRAINT contacts_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE SET NULL;


--
-- Name: donation_receipts donation_receipts_contact_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.donation_receipts
    ADD CONSTRAINT donation_receipts_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id) ON DELETE RESTRICT;


--
-- Name: donations donations_contact_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.donations
    ADD CONSTRAINT donations_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id) ON DELETE SET NULL;


--
-- Name: donations donations_fund_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.donations
    ADD CONSTRAINT donations_fund_id_foreign FOREIGN KEY (fund_id) REFERENCES public.funds(id) ON DELETE SET NULL;


--
-- Name: event_registrations event_registrations_contact_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_registrations
    ADD CONSTRAINT event_registrations_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id) ON DELETE SET NULL;


--
-- Name: event_registrations event_registrations_event_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_registrations
    ADD CONSTRAINT event_registrations_event_id_foreign FOREIGN KEY (event_id) REFERENCES public.events(id) ON DELETE CASCADE;


--
-- Name: events events_author_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_author_id_foreign FOREIGN KEY (author_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: events events_landing_page_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_landing_page_id_foreign FOREIGN KEY (landing_page_id) REFERENCES public.pages(id) ON DELETE SET NULL;


--
-- Name: form_submissions form_submissions_contact_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.form_submissions
    ADD CONSTRAINT form_submissions_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id) ON DELETE SET NULL;


--
-- Name: form_submissions form_submissions_form_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.form_submissions
    ADD CONSTRAINT form_submissions_form_id_foreign FOREIGN KEY (form_id) REFERENCES public.forms(id) ON DELETE CASCADE;


--
-- Name: help_article_routes help_article_routes_help_article_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.help_article_routes
    ADD CONSTRAINT help_article_routes_help_article_id_foreign FOREIGN KEY (help_article_id) REFERENCES public.help_articles(id) ON DELETE CASCADE;


--
-- Name: import_id_maps import_id_maps_import_source_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_id_maps
    ADD CONSTRAINT import_id_maps_import_source_id_foreign FOREIGN KEY (import_source_id) REFERENCES public.import_sources(id) ON DELETE CASCADE;


--
-- Name: import_logs import_logs_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_logs
    ADD CONSTRAINT import_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: import_sessions import_sessions_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_sessions
    ADD CONSTRAINT import_sessions_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: import_sessions import_sessions_import_source_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_sessions
    ADD CONSTRAINT import_sessions_import_source_id_foreign FOREIGN KEY (import_source_id) REFERENCES public.import_sources(id) ON DELETE SET NULL;


--
-- Name: import_sessions import_sessions_imported_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_sessions
    ADD CONSTRAINT import_sessions_imported_by_foreign FOREIGN KEY (imported_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: import_staged_updates import_staged_updates_contact_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_staged_updates
    ADD CONSTRAINT import_staged_updates_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id) ON DELETE CASCADE;


--
-- Name: import_staged_updates import_staged_updates_import_session_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_staged_updates
    ADD CONSTRAINT import_staged_updates_import_session_id_foreign FOREIGN KEY (import_session_id) REFERENCES public.import_sessions(id) ON DELETE CASCADE;


--
-- Name: invitation_tokens invitation_tokens_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitation_tokens
    ADD CONSTRAINT invitation_tokens_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: mailing_list_filters mailing_list_filters_mailing_list_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mailing_list_filters
    ADD CONSTRAINT mailing_list_filters_mailing_list_id_foreign FOREIGN KEY (mailing_list_id) REFERENCES public.mailing_lists(id) ON DELETE CASCADE;


--
-- Name: memberships memberships_contact_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.memberships
    ADD CONSTRAINT memberships_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id) ON DELETE RESTRICT;


--
-- Name: memberships memberships_tier_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.memberships
    ADD CONSTRAINT memberships_tier_id_foreign FOREIGN KEY (tier_id) REFERENCES public.membership_tiers(id) ON DELETE SET NULL;


--
-- Name: model_has_permissions model_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: model_has_roles model_has_roles_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: navigation_items navigation_items_navigation_menu_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.navigation_items
    ADD CONSTRAINT navigation_items_navigation_menu_id_foreign FOREIGN KEY (navigation_menu_id) REFERENCES public.navigation_menus(id) ON DELETE CASCADE;


--
-- Name: navigation_items navigation_items_page_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.navigation_items
    ADD CONSTRAINT navigation_items_page_id_foreign FOREIGN KEY (page_id) REFERENCES public.pages(id) ON DELETE SET NULL;


--
-- Name: navigation_items navigation_items_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.navigation_items
    ADD CONSTRAINT navigation_items_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.navigation_items(id) ON DELETE SET NULL;


--
-- Name: notes notes_author_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notes
    ADD CONSTRAINT notes_author_id_foreign FOREIGN KEY (author_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: page_layouts page_layouts_page_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.page_layouts
    ADD CONSTRAINT page_layouts_page_id_foreign FOREIGN KEY (page_id) REFERENCES public.pages(id) ON DELETE CASCADE;


--
-- Name: page_widgets page_widgets_layout_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.page_widgets
    ADD CONSTRAINT page_widgets_layout_id_foreign FOREIGN KEY (layout_id) REFERENCES public.page_layouts(id) ON DELETE CASCADE;


--
-- Name: page_widgets page_widgets_page_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.page_widgets
    ADD CONSTRAINT page_widgets_page_id_foreign FOREIGN KEY (page_id) REFERENCES public.pages(id) ON DELETE CASCADE;


--
-- Name: page_widgets page_widgets_widget_type_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.page_widgets
    ADD CONSTRAINT page_widgets_widget_type_id_foreign FOREIGN KEY (widget_type_id) REFERENCES public.widget_types(id) ON DELETE RESTRICT;


--
-- Name: pages pages_author_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pages
    ADD CONSTRAINT pages_author_id_foreign FOREIGN KEY (author_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: pages pages_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pages
    ADD CONSTRAINT pages_template_id_foreign FOREIGN KEY (template_id) REFERENCES public.templates(id) ON DELETE SET NULL;


--
-- Name: portal_accounts portal_accounts_contact_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.portal_accounts
    ADD CONSTRAINT portal_accounts_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id) ON DELETE SET NULL;


--
-- Name: posts posts_author_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posts
    ADD CONSTRAINT posts_author_id_foreign FOREIGN KEY (author_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: product_prices product_prices_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_prices
    ADD CONSTRAINT product_prices_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE CASCADE;


--
-- Name: purchases purchases_contact_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchases
    ADD CONSTRAINT purchases_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id) ON DELETE SET NULL;


--
-- Name: purchases purchases_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchases
    ADD CONSTRAINT purchases_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE RESTRICT;


--
-- Name: purchases purchases_product_price_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchases
    ADD CONSTRAINT purchases_product_price_id_foreign FOREIGN KEY (product_price_id) REFERENCES public.product_prices(id) ON DELETE RESTRICT;


--
-- Name: role_has_permissions role_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: taggables taggables_tag_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.taggables
    ADD CONSTRAINT taggables_tag_id_foreign FOREIGN KEY (tag_id) REFERENCES public.tags(id) ON DELETE CASCADE;


--
-- Name: templates templates_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.templates
    ADD CONSTRAINT templates_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: templates templates_footer_page_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.templates
    ADD CONSTRAINT templates_footer_page_id_foreign FOREIGN KEY (footer_page_id) REFERENCES public.pages(id) ON DELETE SET NULL;


--
-- Name: templates templates_header_page_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.templates
    ADD CONSTRAINT templates_header_page_id_foreign FOREIGN KEY (header_page_id) REFERENCES public.pages(id) ON DELETE SET NULL;


--
-- Name: transactions transactions_contact_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transactions
    ADD CONSTRAINT transactions_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id) ON DELETE SET NULL;


--
-- Name: waitlist_entries waitlist_entries_contact_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.waitlist_entries
    ADD CONSTRAINT waitlist_entries_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id) ON DELETE SET NULL;


--
-- Name: waitlist_entries waitlist_entries_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.waitlist_entries
    ADD CONSTRAINT waitlist_entries_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE CASCADE;


--
-- Name: widget_presets widget_presets_widget_type_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.widget_presets
    ADD CONSTRAINT widget_presets_widget_type_id_foreign FOREIGN KEY (widget_type_id) REFERENCES public.widget_types(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict Wl10mQsdBvwkfkk6jiuT8EnlJ0CcTp4dF36jJIl8gsfUsp65cQ1VcfkKFfl8KGg

--
-- PostgreSQL database dump
--

\restrict uAur3B0RkOY0kPm7lcOqrd5VIEfk1voN8lds24Vfuu9ZDxjDC1IggOQghd3ucei

-- Dumped from database version 16.13
-- Dumped by pg_dump version 17.9 (Debian 17.9-0+deb13u1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	2026_03_23_000001_create_membership_tiers_table	1
2	2026_03_23_000002_update_memberships_add_tier_id	1
3	2026_03_23_000003_create_invitation_tokens_table	1
4	2026_03_24_000001_replace_households_with_self_referential	1
5	2026_03_24_000002_create_activity_logs_table	1
6	2026_03_24_100001_split_stripe_settings_keys	1
7	2026_03_24_100002_replace_transaction_donation_id_with_polymorphic	1
8	2026_03_24_200001_create_products_table	1
9	2026_03_24_200002_create_product_prices_table	1
10	2026_03_24_200003_create_allocations_table	1
11	2026_03_24_200004_create_waitlist_entries_table	1
12	2026_03_25_000001_create_products_table	2
13	2026_03_25_000002_create_product_prices_table	2
14	2026_03_25_000004_create_waitlist_entries_table	2
15	2026_03_25_000003_create_purchases_table	3
16	2026_03_25_000005_add_meta_to_activity_logs	4
17	2026_03_25_000001_replace_donations_schema	5
18	2026_03_26_200001_add_fund_id_to_donations_and_restriction_type_to_funds	6
19	2026_03_26_200002_create_donation_receipts_table	7
20	2026_03_26_300001_add_contact_id_to_transactions	8
21	2026_03_28_082001_add_missing_fk_indexes	9
22	2026_03_28_194912_add_author_id_to_pages_table	10
23	2026_03_28_204405_make_pages_author_id_not_nullable	10
24	2026_03_28_204408_add_author_id_to_events_table	10
25	2026_03_29_005427_add_default_open_to_widget_types_table	10
26	2026_03_29_033425_add_parent_and_style_to_page_widgets_table	10
27	2026_03_29_074954_add_status_to_pages_and_drop_is_published	10
28	2026_03_29_092938_alter_media_model_id_to_string	10
29	2026_03_30_010000_add_seo_and_snippet_columns_to_pages_table	10
30	2026_03_30_202450_add_assets_to_widget_types_table	10
31	2026_03_30_220000_create_templates_table	10
32	2026_03_30_220100_add_template_id_to_pages_table	10
33	2026_03_31_034826_remove_theme_keys_from_site_settings	10
34	2026_03_31_120000_add_qb_sync_columns_to_transactions_table	10
35	2026_03_31_140000_add_quickbooks_customer_id_to_contacts_table	10
36	2026_03_31_160000_add_quickbooks_account_id_to_funds_table	10
37	2026_04_01_010000_add_stripe_session_id_and_pending_status_to_event_registrations	10
38	2026_04_01_020000_add_stripe_session_id_to_memberships	10
39	2026_04_01_100000_add_is_archived_to_archivable_tables	10
40	2026_04_01_200000_fix_foreign_key_cascade_rules	10
41	2026_04_02_010000_add_category_to_help_articles_table	10
42	2026_04_03_010000_add_category_and_allowed_page_types_to_widget_types	10
43	2026_04_03_020000_change_category_to_jsonb_on_widget_types	10
44	2026_04_03_030000_add_description_to_widget_types	10
45	2026_04_03_161823_add_full_width_to_widget_types_table	10
46	2026_04_05_224551_add_group_and_subtype_to_widget_config_schemas	11
47	2026_04_06_074630_add_libs_to_widget_type_assets	12
48	2026_04_09_052412_add_required_config_to_widget_types	13
49	2026_04_09_100000_create_page_layouts_and_migrate_column_widgets	13
50	2026_04_09_171811_drop_og_image_path_from_pages	13
51	2026_04_11_000000_rename_style_config_to_appearance_config_on_page_widgets	13
52	2026_04_13_025417_create_widget_presets_table	13
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 52, true);


--
-- PostgreSQL database dump complete
--

\unrestrict uAur3B0RkOY0kPm7lcOqrd5VIEfk1voN8lds24Vfuu9ZDxjDC1IggOQghd3ucei

