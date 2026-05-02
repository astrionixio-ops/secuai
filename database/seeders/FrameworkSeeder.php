<?php

namespace Database\Seeders;

use App\Models\Control;
use App\Models\Framework;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * FrameworkSeeder
 *
 * Seeds the six core compliance frameworks SecuAI supports out of the box, plus their controls.
 * Frameworks and controls are global (not tenant-scoped) reference data. Idempotent: running this
 * seeder multiple times will upsert rows by (framework code) and (framework_id, control_ref).
 *
 * Sources / references:
 *   - SOC 2: AICPA Trust Services Criteria (2017, with 2022 points of focus revisions)
 *   - ISO/IEC 27001:2022 Annex A controls (93 controls across 4 themes)
 *   - HIPAA Security Rule administrative/physical/technical safeguards (45 CFR Part 164 subpart C)
 *   - PCI DSS v4.0 (12 requirements, ~250 sub-requirements; we seed the requirement-level controls
 *     plus key sub-requirements as the operating set)
 *   - NIST Cybersecurity Framework v2.0 (Govern, Identify, Protect, Detect, Respond, Recover)
 *   - GDPR (Regulation (EU) 2016/679) — articles relevant to operational controls
 *
 * Total controls seeded: ~520.
 */
class FrameworkSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedSoc2();
            $this->seedIso27001();
            $this->seedHipaa();
            $this->seedPciDss();
            $this->seedNistCsf();
            $this->seedGdpr();
        });
    }

    private function upsertFramework(array $data): Framework
    {
        return Framework::updateOrCreate(['code' => $data['code']], $data);
    }

    /**
     * @param array<int, array<string, mixed>> $controls
     */
    private function upsertControls(Framework $framework, array $controls): void
    {
        foreach ($controls as $row) {
            Control::updateOrCreate(
                ['framework_id' => $framework->id, 'control_ref' => $row['control_ref']],
                array_merge($row, ['framework_id' => $framework->id])
            );
        }
    }

    // -------------------------------------------------------------------------
    // SOC 2
    // -------------------------------------------------------------------------
    private function seedSoc2(): void
    {
        $f = $this->upsertFramework([
            'code' => 'soc2',
            'name' => 'SOC 2',
            'version' => '2017 TSC (rev. 2022)',
            'issuer' => 'AICPA',
            'category' => 'security',
            'region' => 'us',
            'description' => 'AICPA Trust Services Criteria for Security, Availability, Processing Integrity, Confidentiality, and Privacy.',
            'is_active' => true,
            'metadata' => ['trust_services' => ['security', 'availability', 'processing_integrity', 'confidentiality', 'privacy']],
        ]);

        $controls = [];

        // Common Criteria (CC1 - CC9)
        $cc = [
            'CC1.1' => ['Demonstrates Commitment to Integrity and Ethical Values', 'Governance', 'high'],
            'CC1.2' => ['Exercises Oversight Responsibility', 'Governance', 'high'],
            'CC1.3' => ['Establishes Structure, Authority, and Responsibility', 'Governance', 'high'],
            'CC1.4' => ['Demonstrates Commitment to Competence', 'Governance', 'medium'],
            'CC1.5' => ['Enforces Accountability', 'Governance', 'high'],
            'CC2.1' => ['Uses Relevant, Quality Information', 'Communication', 'medium'],
            'CC2.2' => ['Communicates Internally', 'Communication', 'medium'],
            'CC2.3' => ['Communicates Externally', 'Communication', 'medium'],
            'CC3.1' => ['Specifies Suitable Objectives', 'Risk Assessment', 'high'],
            'CC3.2' => ['Identifies and Analyzes Risk', 'Risk Assessment', 'high'],
            'CC3.3' => ['Assesses Fraud Risk', 'Risk Assessment', 'medium'],
            'CC3.4' => ['Identifies and Assesses Changes', 'Risk Assessment', 'medium'],
            'CC4.1' => ['Selects and Develops Control Activities', 'Monitoring', 'medium'],
            'CC4.2' => ['Evaluates and Communicates Deficiencies', 'Monitoring', 'medium'],
            'CC5.1' => ['Selects Control Activities to Mitigate Risks', 'Control Activities', 'high'],
            'CC5.2' => ['Selects Technology Controls', 'Control Activities', 'high'],
            'CC5.3' => ['Deploys Through Policies and Procedures', 'Control Activities', 'medium'],
            'CC6.1' => ['Logical and Physical Access Controls', 'Logical Access', 'critical'],
            'CC6.2' => ['Manages Credentials and Access Provisioning', 'Logical Access', 'critical'],
            'CC6.3' => ['Removes Access in a Timely Manner', 'Logical Access', 'high'],
            'CC6.4' => ['Restricts Physical Access', 'Physical Access', 'high'],
            'CC6.5' => ['Discontinues Logical and Physical Access', 'Logical Access', 'high'],
            'CC6.6' => ['Implements Network Boundary Protections', 'Network Security', 'critical'],
            'CC6.7' => ['Restricts Information Transmission', 'Data Protection', 'high'],
            'CC6.8' => ['Prevents or Detects Unauthorized Software', 'Endpoint Security', 'high'],
            'CC7.1' => ['Detects and Monitors Configuration Changes', 'Change Management', 'high'],
            'CC7.2' => ['Monitors System Components for Anomalies', 'Monitoring', 'high'],
            'CC7.3' => ['Evaluates and Communicates Security Events', 'Incident Response', 'high'],
            'CC7.4' => ['Responds to Security Incidents', 'Incident Response', 'critical'],
            'CC7.5' => ['Recovers from Security Incidents', 'Incident Response', 'high'],
            'CC8.1' => ['Authorizes, Designs, Develops, and Tests Changes', 'Change Management', 'high'],
            'CC9.1' => ['Identifies and Mitigates Business Disruption Risks', 'Risk Mitigation', 'high'],
            'CC9.2' => ['Manages Vendors and Business Partners', 'Vendor Management', 'high'],
        ];

        foreach ($cc as $ref => [$title, $domain, $sev]) {
            $controls[] = [
                'control_ref' => $ref,
                'title' => $title,
                'domain' => $domain,
                'severity' => $sev,
                'description' => "SOC 2 Common Criteria control $ref: $title.",
                'is_active' => true,
            ];
        }

        // Availability (A1)
        $a = [
            'A1.1' => 'Maintains Capacity to Meet Commitments',
            'A1.2' => 'Designs and Implements Environmental Protections',
            'A1.3' => 'Tests Recovery Plan Procedures',
        ];
        foreach ($a as $ref => $title) {
            $controls[] = ['control_ref' => $ref, 'title' => $title, 'domain' => 'Availability', 'severity' => 'high', 'description' => "SOC 2 Availability $ref: $title.", 'is_active' => true];
        }

        // Confidentiality (C1)
        $controls[] = ['control_ref' => 'C1.1', 'title' => 'Identifies and Maintains Confidential Information', 'domain' => 'Confidentiality', 'severity' => 'high', 'description' => 'SOC 2 Confidentiality C1.1.', 'is_active' => true];
        $controls[] = ['control_ref' => 'C1.2', 'title' => 'Disposes of Confidential Information', 'domain' => 'Confidentiality', 'severity' => 'high', 'description' => 'SOC 2 Confidentiality C1.2.', 'is_active' => true];

        // Processing Integrity (PI1)
        $pi = [
            'PI1.1' => 'Obtains Quality Information for Processing',
            'PI1.2' => 'Processes Inputs Completely and Accurately',
            'PI1.3' => 'Processes Data Completely and Accurately',
            'PI1.4' => 'Outputs Are Complete, Accurate, and Timely',
            'PI1.5' => 'Stores Inputs and Outputs Completely and Accurately',
        ];
        foreach ($pi as $ref => $title) {
            $controls[] = ['control_ref' => $ref, 'title' => $title, 'domain' => 'Processing Integrity', 'severity' => 'medium', 'description' => "SOC 2 Processing Integrity $ref: $title.", 'is_active' => true];
        }

        // Privacy (P1 - P8)
        $p = [
            'P1.1' => 'Notice and Communication of Objectives',
            'P2.1' => 'Choice and Consent',
            'P3.1' => 'Collection Limited to Identified Purpose',
            'P3.2' => 'Explicit Consent for Sensitive Information',
            'P4.1' => 'Use, Retention, and Disposal',
            'P4.2' => 'Retains Personal Information per Stated Purpose',
            'P4.3' => 'Securely Disposes of Personal Information',
            'P5.1' => 'Access for Identified and Authenticated Individuals',
            'P5.2' => 'Updates and Corrections to Personal Information',
            'P6.1' => 'Disclosure to Third Parties Limited to Stated Purpose',
            'P6.2' => 'Authorized Disclosures',
            'P6.3' => 'Tracks Disclosures',
            'P6.4' => 'Notifications of Personal Information Disclosure',
            'P6.5' => 'Tracks External Disclosures of Personal Information',
            'P6.6' => 'Notification of Breaches and Incidents',
            'P6.7' => 'Tracks Disclosures and Required Notifications',
            'P7.1' => 'Quality of Personal Information',
            'P8.1' => 'Inquiry, Complaint, and Dispute Resolution',
        ];
        foreach ($p as $ref => $title) {
            $controls[] = ['control_ref' => $ref, 'title' => $title, 'domain' => 'Privacy', 'severity' => 'high', 'description' => "SOC 2 Privacy $ref: $title.", 'is_active' => true];
        }

        $this->upsertControls($f, $controls);
    }

    // -------------------------------------------------------------------------
    // ISO/IEC 27001:2022 Annex A — 93 controls in 4 themes
    // -------------------------------------------------------------------------
    private function seedIso27001(): void
    {
        $f = $this->upsertFramework([
            'code' => 'iso27001',
            'name' => 'ISO/IEC 27001',
            'version' => '2022',
            'issuer' => 'ISO/IEC',
            'category' => 'security',
            'region' => 'global',
            'description' => 'International standard for information security management systems (ISMS).',
            'is_active' => true,
        ]);

        $controls = [];

        // Annex A.5 Organizational controls (37)
        $orgs = [
            'A.5.1' => 'Policies for information security',
            'A.5.2' => 'Information security roles and responsibilities',
            'A.5.3' => 'Segregation of duties',
            'A.5.4' => 'Management responsibilities',
            'A.5.5' => 'Contact with authorities',
            'A.5.6' => 'Contact with special interest groups',
            'A.5.7' => 'Threat intelligence',
            'A.5.8' => 'Information security in project management',
            'A.5.9' => 'Inventory of information and other associated assets',
            'A.5.10' => 'Acceptable use of information and other associated assets',
            'A.5.11' => 'Return of assets',
            'A.5.12' => 'Classification of information',
            'A.5.13' => 'Labelling of information',
            'A.5.14' => 'Information transfer',
            'A.5.15' => 'Access control',
            'A.5.16' => 'Identity management',
            'A.5.17' => 'Authentication information',
            'A.5.18' => 'Access rights',
            'A.5.19' => 'Information security in supplier relationships',
            'A.5.20' => 'Addressing information security within supplier agreements',
            'A.5.21' => 'Managing information security in the ICT supply chain',
            'A.5.22' => 'Monitoring, review and change management of supplier services',
            'A.5.23' => 'Information security for use of cloud services',
            'A.5.24' => 'Information security incident management planning and preparation',
            'A.5.25' => 'Assessment and decision on information security events',
            'A.5.26' => 'Response to information security incidents',
            'A.5.27' => 'Learning from information security incidents',
            'A.5.28' => 'Collection of evidence',
            'A.5.29' => 'Information security during disruption',
            'A.5.30' => 'ICT readiness for business continuity',
            'A.5.31' => 'Legal, statutory, regulatory and contractual requirements',
            'A.5.32' => 'Intellectual property rights',
            'A.5.33' => 'Protection of records',
            'A.5.34' => 'Privacy and protection of PII',
            'A.5.35' => 'Independent review of information security',
            'A.5.36' => 'Compliance with policies, rules and standards for information security',
            'A.5.37' => 'Documented operating procedures',
        ];
        foreach ($orgs as $ref => $title) {
            $controls[] = ['control_ref' => $ref, 'title' => $title, 'domain' => 'Organizational', 'severity' => 'high', 'description' => "ISO 27001:2022 $ref: $title.", 'is_active' => true];
        }

        // Annex A.6 People controls (8)
        $people = [
            'A.6.1' => 'Screening',
            'A.6.2' => 'Terms and conditions of employment',
            'A.6.3' => 'Information security awareness, education and training',
            'A.6.4' => 'Disciplinary process',
            'A.6.5' => 'Responsibilities after termination or change of employment',
            'A.6.6' => 'Confidentiality or non-disclosure agreements',
            'A.6.7' => 'Remote working',
            'A.6.8' => 'Information security event reporting',
        ];
        foreach ($people as $ref => $title) {
            $controls[] = ['control_ref' => $ref, 'title' => $title, 'domain' => 'People', 'severity' => 'medium', 'description' => "ISO 27001:2022 $ref: $title.", 'is_active' => true];
        }

        // Annex A.7 Physical controls (14)
        $phys = [
            'A.7.1' => 'Physical security perimeters',
            'A.7.2' => 'Physical entry',
            'A.7.3' => 'Securing offices, rooms and facilities',
            'A.7.4' => 'Physical security monitoring',
            'A.7.5' => 'Protecting against physical and environmental threats',
            'A.7.6' => 'Working in secure areas',
            'A.7.7' => 'Clear desk and clear screen',
            'A.7.8' => 'Equipment siting and protection',
            'A.7.9' => 'Security of assets off-premises',
            'A.7.10' => 'Storage media',
            'A.7.11' => 'Supporting utilities',
            'A.7.12' => 'Cabling security',
            'A.7.13' => 'Equipment maintenance',
            'A.7.14' => 'Secure disposal or re-use of equipment',
        ];
        foreach ($phys as $ref => $title) {
            $controls[] = ['control_ref' => $ref, 'title' => $title, 'domain' => 'Physical', 'severity' => 'medium', 'description' => "ISO 27001:2022 $ref: $title.", 'is_active' => true];
        }

        // Annex A.8 Technological controls (34)
        $tech = [
            'A.8.1' => 'User end point devices',
            'A.8.2' => 'Privileged access rights',
            'A.8.3' => 'Information access restriction',
            'A.8.4' => 'Access to source code',
            'A.8.5' => 'Secure authentication',
            'A.8.6' => 'Capacity management',
            'A.8.7' => 'Protection against malware',
            'A.8.8' => 'Management of technical vulnerabilities',
            'A.8.9' => 'Configuration management',
            'A.8.10' => 'Information deletion',
            'A.8.11' => 'Data masking',
            'A.8.12' => 'Data leakage prevention',
            'A.8.13' => 'Information backup',
            'A.8.14' => 'Redundancy of information processing facilities',
            'A.8.15' => 'Logging',
            'A.8.16' => 'Monitoring activities',
            'A.8.17' => 'Clock synchronization',
            'A.8.18' => 'Use of privileged utility programs',
            'A.8.19' => 'Installation of software on operational systems',
            'A.8.20' => 'Networks security',
            'A.8.21' => 'Security of network services',
            'A.8.22' => 'Segregation of networks',
            'A.8.23' => 'Web filtering',
            'A.8.24' => 'Use of cryptography',
            'A.8.25' => 'Secure development life cycle',
            'A.8.26' => 'Application security requirements',
            'A.8.27' => 'Secure system architecture and engineering principles',
            'A.8.28' => 'Secure coding',
            'A.8.29' => 'Security testing in development and acceptance',
            'A.8.30' => 'Outsourced development',
            'A.8.31' => 'Separation of development, test and production environments',
            'A.8.32' => 'Change management',
            'A.8.33' => 'Test information',
            'A.8.34' => 'Protection of information systems during audit testing',
        ];
        foreach ($tech as $ref => $title) {
            $controls[] = ['control_ref' => $ref, 'title' => $title, 'domain' => 'Technological', 'severity' => 'high', 'description' => "ISO 27001:2022 $ref: $title.", 'is_active' => true];
        }

        $this->upsertControls($f, $controls);
    }

    // -------------------------------------------------------------------------
    // HIPAA Security Rule (45 CFR §164 subpart C)
    // -------------------------------------------------------------------------
    private function seedHipaa(): void
    {
        $f = $this->upsertFramework([
            'code' => 'hipaa',
            'name' => 'HIPAA Security Rule',
            'version' => '45 CFR Part 164 Subpart C',
            'issuer' => 'HHS / OCR',
            'category' => 'privacy',
            'region' => 'us',
            'description' => 'US health information privacy and security regulation safeguards for ePHI.',
            'is_active' => true,
        ]);

        $controls = [];

        // Administrative Safeguards §164.308
        $admin = [
            '164.308(a)(1)(i)' => ['Security Management Process', 'critical'],
            '164.308(a)(1)(ii)(A)' => ['Risk Analysis', 'critical'],
            '164.308(a)(1)(ii)(B)' => ['Risk Management', 'critical'],
            '164.308(a)(1)(ii)(C)' => ['Sanction Policy', 'medium'],
            '164.308(a)(1)(ii)(D)' => ['Information System Activity Review', 'high'],
            '164.308(a)(2)' => ['Assigned Security Responsibility', 'high'],
            '164.308(a)(3)(i)' => ['Workforce Security', 'high'],
            '164.308(a)(3)(ii)(A)' => ['Authorization and/or Supervision', 'high'],
            '164.308(a)(3)(ii)(B)' => ['Workforce Clearance Procedure', 'medium'],
            '164.308(a)(3)(ii)(C)' => ['Termination Procedures', 'high'],
            '164.308(a)(4)(i)' => ['Information Access Management', 'critical'],
            '164.308(a)(4)(ii)(A)' => ['Isolating Health Care Clearinghouse Functions', 'medium'],
            '164.308(a)(4)(ii)(B)' => ['Access Authorization', 'high'],
            '164.308(a)(4)(ii)(C)' => ['Access Establishment and Modification', 'high'],
            '164.308(a)(5)(i)' => ['Security Awareness and Training', 'high'],
            '164.308(a)(5)(ii)(A)' => ['Security Reminders', 'medium'],
            '164.308(a)(5)(ii)(B)' => ['Protection from Malicious Software', 'high'],
            '164.308(a)(5)(ii)(C)' => ['Log-in Monitoring', 'high'],
            '164.308(a)(5)(ii)(D)' => ['Password Management', 'high'],
            '164.308(a)(6)(i)' => ['Security Incident Procedures', 'critical'],
            '164.308(a)(6)(ii)' => ['Response and Reporting', 'critical'],
            '164.308(a)(7)(i)' => ['Contingency Plan', 'high'],
            '164.308(a)(7)(ii)(A)' => ['Data Backup Plan', 'critical'],
            '164.308(a)(7)(ii)(B)' => ['Disaster Recovery Plan', 'critical'],
            '164.308(a)(7)(ii)(C)' => ['Emergency Mode Operation Plan', 'high'],
            '164.308(a)(7)(ii)(D)' => ['Testing and Revision Procedures', 'medium'],
            '164.308(a)(7)(ii)(E)' => ['Applications and Data Criticality Analysis', 'medium'],
            '164.308(a)(8)' => ['Evaluation', 'high'],
            '164.308(b)(1)' => ['Business Associate Contracts', 'high'],
        ];
        foreach ($admin as $ref => [$title, $sev]) {
            $controls[] = ['control_ref' => $ref, 'title' => $title, 'domain' => 'Administrative Safeguards', 'severity' => $sev, 'description' => "HIPAA $ref: $title.", 'is_active' => true];
        }

        // Physical Safeguards §164.310
        $physical = [
            '164.310(a)(1)' => ['Facility Access Controls', 'high'],
            '164.310(a)(2)(i)' => ['Contingency Operations', 'medium'],
            '164.310(a)(2)(ii)' => ['Facility Security Plan', 'medium'],
            '164.310(a)(2)(iii)' => ['Access Control and Validation Procedures', 'high'],
            '164.310(a)(2)(iv)' => ['Maintenance Records', 'medium'],
            '164.310(b)' => ['Workstation Use', 'medium'],
            '164.310(c)' => ['Workstation Security', 'medium'],
            '164.310(d)(1)' => ['Device and Media Controls', 'high'],
            '164.310(d)(2)(i)' => ['Disposal', 'high'],
            '164.310(d)(2)(ii)' => ['Media Re-use', 'high'],
            '164.310(d)(2)(iii)' => ['Accountability', 'medium'],
            '164.310(d)(2)(iv)' => ['Data Backup and Storage', 'high'],
        ];
        foreach ($physical as $ref => [$title, $sev]) {
            $controls[] = ['control_ref' => $ref, 'title' => $title, 'domain' => 'Physical Safeguards', 'severity' => $sev, 'description' => "HIPAA $ref: $title.", 'is_active' => true];
        }

        // Technical Safeguards §164.312
        $technical = [
            '164.312(a)(1)' => ['Access Control', 'critical'],
            '164.312(a)(2)(i)' => ['Unique User Identification', 'critical'],
            '164.312(a)(2)(ii)' => ['Emergency Access Procedure', 'high'],
            '164.312(a)(2)(iii)' => ['Automatic Logoff', 'medium'],
            '164.312(a)(2)(iv)' => ['Encryption and Decryption', 'critical'],
            '164.312(b)' => ['Audit Controls', 'critical'],
            '164.312(c)(1)' => ['Integrity', 'high'],
            '164.312(c)(2)' => ['Mechanism to Authenticate ePHI', 'high'],
            '164.312(d)' => ['Person or Entity Authentication', 'critical'],
            '164.312(e)(1)' => ['Transmission Security', 'critical'],
            '164.312(e)(2)(i)' => ['Integrity Controls (transmission)', 'high'],
            '164.312(e)(2)(ii)' => ['Encryption (transmission)', 'critical'],
        ];
        foreach ($technical as $ref => [$title, $sev]) {
            $controls[] = ['control_ref' => $ref, 'title' => $title, 'domain' => 'Technical Safeguards', 'severity' => $sev, 'description' => "HIPAA $ref: $title.", 'is_active' => true];
        }

        // Breach Notification §164.400 series (selected operational controls)
        $breach = [
            '164.404' => ['Notification to Individuals', 'high'],
            '164.406' => ['Notification to the Media', 'medium'],
            '164.408' => ['Notification to the Secretary', 'high'],
            '164.410' => ['Notification by a Business Associate', 'high'],
            '164.414' => ['Administrative Requirements and Burden of Proof', 'medium'],
        ];
        foreach ($breach as $ref => [$title, $sev]) {
            $controls[] = ['control_ref' => $ref, 'title' => $title, 'domain' => 'Breach Notification', 'severity' => $sev, 'description' => "HIPAA $ref: $title.", 'is_active' => true];
        }

        $this->upsertControls($f, $controls);
    }

    // -------------------------------------------------------------------------
    // PCI DSS v4.0 — 12 requirements with key sub-requirements
    // -------------------------------------------------------------------------
    private function seedPciDss(): void
    {
        $f = $this->upsertFramework([
            'code' => 'pci_dss',
            'name' => 'PCI DSS',
            'version' => '4.0',
            'issuer' => 'PCI SSC',
            'category' => 'industry',
            'region' => 'global',
            'description' => 'Payment Card Industry Data Security Standard for entities that store, process, or transmit cardholder data.',
            'is_active' => true,
        ]);

        $controls = [];

        $requirements = [
            '1' => ['Install and Maintain Network Security Controls', 'Network Security'],
            '2' => ['Apply Secure Configurations to All System Components', 'Configuration'],
            '3' => ['Protect Stored Account Data', 'Data Protection'],
            '4' => ['Protect Cardholder Data with Strong Cryptography During Transmission', 'Data Protection'],
            '5' => ['Protect All Systems and Networks from Malicious Software', 'Endpoint Security'],
            '6' => ['Develop and Maintain Secure Systems and Software', 'Secure Development'],
            '7' => ['Restrict Access to System Components and Cardholder Data by Business Need to Know', 'Access Control'],
            '8' => ['Identify Users and Authenticate Access to System Components', 'Authentication'],
            '9' => ['Restrict Physical Access to Cardholder Data', 'Physical Security'],
            '10' => ['Log and Monitor All Access to System Components and Cardholder Data', 'Logging & Monitoring'],
            '11' => ['Test Security of Systems and Networks Regularly', 'Testing'],
            '12' => ['Support Information Security with Organizational Policies and Programs', 'Governance'],
        ];

        foreach ($requirements as $ref => [$title, $domain]) {
            $controls[] = ['control_ref' => "Req $ref", 'title' => $title, 'domain' => $domain, 'severity' => 'critical', 'description' => "PCI DSS v4.0 Requirement $ref: $title.", 'is_active' => true];
        }

        // Key sub-requirements (operational set)
        $sub = [
            '1.2.1' => ['Network security controls (NSC) configurations are defined', 'Network Security', 'high'],
            '1.2.2' => ['Changes to NSC configurations are managed', 'Network Security', 'high'],
            '1.2.5' => ['Ports, protocols, and services in use are identified, approved, and have a defined business need', 'Network Security', 'high'],
            '1.3.1' => ['Inbound traffic to the CDE is restricted', 'Network Security', 'critical'],
            '1.3.2' => ['Outbound traffic from the CDE is restricted', 'Network Security', 'high'],
            '1.4.1' => ['NSCs are implemented between trusted and untrusted networks', 'Network Security', 'critical'],
            '2.2.1' => ['Configuration standards are developed, implemented, and maintained', 'Configuration', 'high'],
            '2.2.2' => ['Vendor default accounts are managed', 'Configuration', 'critical'],
            '2.2.4' => ['Only necessary services, protocols, daemons, and functions are enabled', 'Configuration', 'high'],
            '2.3.1' => ['Wireless vendor defaults are changed', 'Configuration', 'high'],
            '3.2.1' => ['Account data storage is kept to a minimum', 'Data Protection', 'critical'],
            '3.3.1' => ['SAD is not retained after authorization', 'Data Protection', 'critical'],
            '3.4.1' => ['PAN is masked when displayed', 'Data Protection', 'critical'],
            '3.5.1' => ['PAN is rendered unreadable wherever stored', 'Data Protection', 'critical'],
            '3.6.1' => ['Cryptographic keys used to protect stored account data are secured', 'Cryptography', 'critical'],
            '3.7.1' => ['Key-management policies and procedures are documented', 'Cryptography', 'high'],
            '4.2.1' => ['Strong cryptography and security protocols are implemented for transmissions', 'Cryptography', 'critical'],
            '4.2.2' => ['PAN is secured with strong cryptography during transmission via end-user messaging technologies', 'Cryptography', 'high'],
            '5.2.1' => ['An anti-malware solution is deployed on all system components', 'Endpoint Security', 'high'],
            '5.3.1' => ['Anti-malware mechanisms are kept current and active', 'Endpoint Security', 'high'],
            '5.4.1' => ['Anti-phishing mechanisms are deployed', 'Endpoint Security', 'medium'],
            '6.2.1' => ['Bespoke and custom software are developed securely', 'Secure Development', 'high'],
            '6.3.1' => ['Security vulnerabilities are identified and managed', 'Vulnerability Management', 'critical'],
            '6.3.3' => ['All system components are protected from known vulnerabilities by patches', 'Vulnerability Management', 'critical'],
            '6.4.1' => ['Public-facing web applications are protected against attacks', 'Application Security', 'critical'],
            '6.5.1' => ['Changes to all system components are managed securely', 'Change Management', 'high'],
            '7.2.1' => ['An access control model is defined', 'Access Control', 'high'],
            '7.2.2' => ['Access is assigned based on job classification and least privilege', 'Access Control', 'critical'],
            '7.2.4' => ['User accounts and access are reviewed periodically', 'Access Control', 'high'],
            '7.2.5' => ['Application and system accounts are managed and reviewed', 'Access Control', 'high'],
            '8.2.1' => ['All users are assigned a unique ID', 'Authentication', 'critical'],
            '8.3.1' => ['Strong authentication is enforced', 'Authentication', 'critical'],
            '8.3.6' => ['Passwords meet minimum length and complexity', 'Authentication', 'high'],
            '8.4.1' => ['MFA is implemented for non-console administrative access into the CDE', 'Authentication', 'critical'],
            '8.4.2' => ['MFA is implemented for all access into the CDE', 'Authentication', 'critical'],
            '8.5.1' => ['MFA systems are implemented to prevent misuse', 'Authentication', 'high'],
            '9.2.1' => ['Appropriate facility entry controls are in place', 'Physical Security', 'high'],
            '9.3.1' => ['Procedures for authorizing personnel are implemented', 'Physical Security', 'medium'],
            '9.4.1' => ['Media with cardholder data is protected', 'Physical Security', 'high'],
            '9.5.1' => ['POI devices are protected from tampering', 'Physical Security', 'high'],
            '10.2.1' => ['Audit logs are enabled and active for all system components', 'Logging & Monitoring', 'critical'],
            '10.2.2' => ['Audit logs capture required user and event details', 'Logging & Monitoring', 'high'],
            '10.3.1' => ['Audit log files are protected from unauthorized modification', 'Logging & Monitoring', 'high'],
            '10.4.1' => ['Audit logs are reviewed at least daily', 'Logging & Monitoring', 'high'],
            '10.5.1' => ['Audit logs are retained for at least 12 months', 'Logging & Monitoring', 'high'],
            '10.7.1' => ['Failures of critical security control systems are detected and responded to', 'Logging & Monitoring', 'critical'],
            '11.3.1' => ['Internal vulnerability scans are performed', 'Testing', 'high'],
            '11.3.2' => ['External vulnerability scans are performed', 'Testing', 'high'],
            '11.4.1' => ['External and internal penetration testing is regularly performed', 'Testing', 'high'],
            '11.5.1' => ['IDS/IPS techniques are used to detect or prevent intrusions', 'Testing', 'high'],
            '11.6.1' => ['Change-and-tamper detection mechanism is deployed for payment pages', 'Testing', 'high'],
            '12.1.1' => ['Information security policy is established, published, maintained, and disseminated', 'Governance', 'high'],
            '12.3.1' => ['Risk assessment is performed for customized approach controls', 'Governance', 'medium'],
            '12.5.1' => ['Inventory of system components in scope for PCI DSS is maintained', 'Governance', 'high'],
            '12.6.1' => ['Security awareness program is implemented', 'Governance', 'medium'],
            '12.8.1' => ['List of TPSPs with whom account data is shared is maintained', 'Vendor Management', 'high'],
            '12.10.1' => ['Incident response plan exists and is ready to be activated', 'Incident Response', 'critical'],
        ];

        foreach ($sub as $ref => [$title, $domain, $sev]) {
            $controls[] = ['control_ref' => $ref, 'title' => $title, 'domain' => $domain, 'severity' => $sev, 'description' => "PCI DSS v4.0 $ref: $title.", 'is_active' => true];
        }

        $this->upsertControls($f, $controls);
    }

    // -------------------------------------------------------------------------
    // NIST CSF 2.0
    // -------------------------------------------------------------------------
    private function seedNistCsf(): void
    {
        $f = $this->upsertFramework([
            'code' => 'nist_csf',
            'name' => 'NIST Cybersecurity Framework',
            'version' => '2.0',
            'issuer' => 'NIST',
            'category' => 'security',
            'region' => 'us',
            'description' => 'Voluntary framework of standards, guidelines, and best practices to manage cybersecurity-related risk.',
            'is_active' => true,
        ]);

        $controls = [];

        // GOVERN (GV)
        $gv = [
            'GV.OC-01' => 'Organizational mission is understood and informs risk management',
            'GV.OC-02' => 'Internal and external stakeholders are determined',
            'GV.OC-03' => 'Legal, regulatory, and contractual requirements are understood',
            'GV.OC-04' => 'Critical objectives, capabilities, and services are determined',
            'GV.OC-05' => 'Outcomes, capabilities, and services depended on are determined',
            'GV.RM-01' => 'Risk management objectives are established and agreed to',
            'GV.RM-02' => 'Risk appetite and risk tolerance statements are established',
            'GV.RM-03' => 'Cybersecurity risk management activities are integrated into ERM',
            'GV.RM-04' => 'Strategic direction describes risk response options',
            'GV.RM-05' => 'Communication lines exist for cybersecurity risks',
            'GV.RM-06' => 'A standardized method for calculating, documenting, and communicating risks is established',
            'GV.RM-07' => 'Strategic opportunities are characterized and included',
            'GV.RR-01' => 'Organizational leadership is responsible and accountable for cybersecurity risk',
            'GV.RR-02' => 'Roles, responsibilities, and authorities related to cybersecurity risk are established',
            'GV.RR-03' => 'Adequate resources are allocated commensurate with the strategy',
            'GV.RR-04' => 'Cybersecurity is included in human resources practices',
            'GV.PO-01' => 'Cybersecurity policy is established based on context, strategy, and priorities',
            'GV.PO-02' => 'Policy is reviewed, updated, communicated, and enforced',
            'GV.OV-01' => 'Cybersecurity strategy outcomes are reviewed',
            'GV.OV-02' => 'Strategy is reviewed and adjusted',
            'GV.OV-03' => 'Cybersecurity risk management performance is monitored',
            'GV.SC-01' => 'Cybersecurity supply chain risk management program is established',
            'GV.SC-02' => 'Cybersecurity roles for suppliers are established',
            'GV.SC-03' => 'Cybersecurity supply chain risk management is integrated into broader programs',
            'GV.SC-04' => 'Suppliers are known and prioritized by criticality',
            'GV.SC-05' => 'Requirements to address cybersecurity risks in supply chains are established',
            'GV.SC-06' => 'Planning and due diligence are performed to reduce risks before relationships',
            'GV.SC-07' => 'Risks posed by suppliers and their products are understood and managed',
            'GV.SC-08' => 'Relevant suppliers are included in incident planning, response, and recovery',
            'GV.SC-09' => 'Supply chain security practices are integrated into the technology lifecycle',
            'GV.SC-10' => 'Cybersecurity supply chain risk management plans include provisions for partnership winddown',
        ];
        foreach ($gv as $ref => $title) {
            $controls[] = ['control_ref' => $ref, 'title' => $title, 'domain' => 'Govern', 'severity' => 'high', 'description' => "NIST CSF 2.0 $ref: $title.", 'is_active' => true];
        }

        // IDENTIFY (ID)
        $id = [
            'ID.AM-01' => 'Inventories of hardware managed by the organization are maintained',
            'ID.AM-02' => 'Inventories of software, services, and systems are maintained',
            'ID.AM-03' => 'Representations of authorized network communication and data flows are maintained',
            'ID.AM-04' => 'Inventories of services provided by suppliers are maintained',
            'ID.AM-05' => 'Assets are prioritized based on classification, criticality, and business value',
            'ID.AM-07' => 'Inventories of data and metadata are maintained',
            'ID.AM-08' => 'Systems, hardware, software, and services are managed throughout their lifecycles',
            'ID.RA-01' => 'Vulnerabilities in assets are identified, validated, and recorded',
            'ID.RA-02' => 'Cyber threat intelligence is received from information sharing forums',
            'ID.RA-03' => 'Internal and external threats to the organization are identified and recorded',
            'ID.RA-04' => 'Potential impacts and likelihoods of threats are identified',
            'ID.RA-05' => 'Threats, vulnerabilities, likelihoods, and impacts are used to understand inherent risk',
            'ID.RA-06' => 'Risk responses are chosen, prioritized, planned, tracked, and communicated',
            'ID.RA-07' => 'Changes and exceptions are managed, assessed for risk impact, recorded, and tracked',
            'ID.RA-08' => 'Processes for receiving, analyzing, and responding to vulnerability disclosures are established',
            'ID.RA-09' => 'The authenticity and integrity of hardware and software are assessed before acquisition',
            'ID.RA-10' => 'Critical suppliers are assessed prior to acquisition',
            'ID.IM-01' => 'Improvements are identified from evaluations',
            'ID.IM-02' => 'Improvements are identified from security tests and exercises',
            'ID.IM-03' => 'Improvements are identified from execution of operational processes, procedures, and activities',
            'ID.IM-04' => 'Incident response plans and other cybersecurity plans are established, communicated, maintained, and improved',
        ];
        foreach ($id as $ref => $title) {
            $controls[] = ['control_ref' => $ref, 'title' => $title, 'domain' => 'Identify', 'severity' => 'high', 'description' => "NIST CSF 2.0 $ref: $title.", 'is_active' => true];
        }

        // PROTECT (PR)
        $pr = [
            'PR.AA-01' => 'Identities and credentials for authorized users, services, and hardware are managed',
            'PR.AA-02' => 'Identities are proofed and bound to credentials',
            'PR.AA-03' => 'Users, services, and hardware are authenticated',
            'PR.AA-04' => 'Identity assertions are protected, conveyed, and verified',
            'PR.AA-05' => 'Access permissions, entitlements, and authorizations are defined and enforced',
            'PR.AA-06' => 'Physical access to assets is managed',
            'PR.AT-01' => 'Personnel are provided awareness and training',
            'PR.AT-02' => 'Individuals in specialized roles are provided awareness and training',
            'PR.DS-01' => 'The confidentiality, integrity, and availability of data-at-rest are protected',
            'PR.DS-02' => 'The confidentiality, integrity, and availability of data-in-transit are protected',
            'PR.DS-10' => 'The confidentiality, integrity, and availability of data-in-use are protected',
            'PR.DS-11' => 'Backups of data are created, protected, maintained, and tested',
            'PR.PS-01' => 'Configuration management practices are established and applied',
            'PR.PS-02' => 'Software is maintained, replaced, and removed commensurate with risk',
            'PR.PS-03' => 'Hardware is maintained, replaced, and removed commensurate with risk',
            'PR.PS-04' => 'Log records are generated and made available for continuous monitoring',
            'PR.PS-05' => 'Installation and execution of unauthorized software are prevented',
            'PR.PS-06' => 'Secure software development practices are integrated',
            'PR.IR-01' => 'Networks and environments are protected from unauthorized logical access and usage',
            'PR.IR-02' => 'The organization’s technology assets are protected from environmental threats',
            'PR.IR-03' => 'Mechanisms are implemented to achieve resilience requirements',
            'PR.IR-04' => 'Adequate resource capacity to ensure availability is maintained',
        ];
        foreach ($pr as $ref => $title) {
            $controls[] = ['control_ref' => $ref, 'title' => $title, 'domain' => 'Protect', 'severity' => 'high', 'description' => "NIST CSF 2.0 $ref: $title.", 'is_active' => true];
        }

        // DETECT (DE)
        $de = [
            'DE.CM-01' => 'Networks and network services are monitored',
            'DE.CM-02' => 'The physical environment is monitored',
            'DE.CM-03' => 'Personnel activity and technology usage are monitored',
            'DE.CM-06' => 'External service provider activities and services are monitored',
            'DE.CM-09' => 'Computing hardware and software, runtime environments, and their data are monitored',
            'DE.AE-02' => 'Potentially adverse events are analyzed to better understand activities',
            'DE.AE-03' => 'Information is correlated from multiple sources',
            'DE.AE-04' => 'The estimated impact and scope of adverse events are understood',
            'DE.AE-06' => 'Information on adverse events is provided to authorized staff and tools',
            'DE.AE-07' => 'Cyber threat intelligence and other contextual information are integrated',
            'DE.AE-08' => 'Incidents are declared when adverse events meet defined incident criteria',
        ];
        foreach ($de as $ref => $title) {
            $controls[] = ['control_ref' => $ref, 'title' => $title, 'domain' => 'Detect', 'severity' => 'high', 'description' => "NIST CSF 2.0 $ref: $title.", 'is_active' => true];
        }

        // RESPOND (RS)
        $rs = [
            'RS.MA-01' => 'The incident response plan is executed once an incident is declared',
            'RS.MA-02' => 'Incident reports are triaged and validated',
            'RS.MA-03' => 'Incidents are categorized and prioritized',
            'RS.MA-04' => 'Incidents are escalated or elevated as needed',
            'RS.MA-05' => 'Criteria for initiating recovery are applied',
            'RS.AN-03' => 'Analysis is performed to establish what has taken place during an incident',
            'RS.AN-06' => 'Actions performed during an investigation are recorded',
            'RS.AN-07' => 'Incident data and metadata are collected, and their integrity and provenance are preserved',
            'RS.AN-08' => 'An incident\'s magnitude is estimated and validated',
            'RS.CO-02' => 'Internal and external stakeholders are notified of incidents',
            'RS.CO-03' => 'Information is shared with designated stakeholders',
            'RS.MI-01' => 'Incidents are contained',
            'RS.MI-02' => 'Incidents are eradicated',
        ];
        foreach ($rs as $ref => $title) {
            $controls[] = ['control_ref' => $ref, 'title' => $title, 'domain' => 'Respond', 'severity' => 'high', 'description' => "NIST CSF 2.0 $ref: $title.", 'is_active' => true];
        }

        // RECOVER (RC)
        $rc = [
            'RC.RP-01' => 'The recovery portion of the incident response plan is executed',
            'RC.RP-02' => 'Recovery actions are selected, scoped, prioritized, and performed',
            'RC.RP-03' => 'The integrity of backups and other restoration assets is verified',
            'RC.RP-04' => 'Critical mission functions and risk are considered when restoring',
            'RC.RP-05' => 'The integrity of restored assets is verified, systems are restored, and normal operations are confirmed',
            'RC.RP-06' => 'The end of incident recovery is declared and documented',
            'RC.CO-03' => 'Recovery activities and progress are communicated to stakeholders',
            'RC.CO-04' => 'Public updates on incident recovery are shared using approved methods and messaging',
        ];
        foreach ($rc as $ref => $title) {
            $controls[] = ['control_ref' => $ref, 'title' => $title, 'domain' => 'Recover', 'severity' => 'high', 'description' => "NIST CSF 2.0 $ref: $title.", 'is_active' => true];
        }

        $this->upsertControls($f, $controls);
    }

    // -------------------------------------------------------------------------
    // GDPR — operational controls derived from articles
    // -------------------------------------------------------------------------
    private function seedGdpr(): void
    {
        $f = $this->upsertFramework([
            'code' => 'gdpr',
            'name' => 'GDPR',
            'version' => '2016/679',
            'issuer' => 'European Parliament',
            'category' => 'privacy',
            'region' => 'eu',
            'description' => 'EU General Data Protection Regulation — protection of natural persons regarding the processing of personal data.',
            'is_active' => true,
        ]);

        $controls = [];

        $articles = [
            'Art.5' => ['Principles relating to processing of personal data', 'Principles', 'critical'],
            'Art.6' => ['Lawfulness of processing', 'Principles', 'critical'],
            'Art.7' => ['Conditions for consent', 'Principles', 'high'],
            'Art.8' => ['Conditions applicable to child\'s consent in relation to information society services', 'Principles', 'high'],
            'Art.9' => ['Processing of special categories of personal data', 'Principles', 'critical'],
            'Art.10' => ['Processing of personal data relating to criminal convictions and offences', 'Principles', 'high'],
            'Art.12' => ['Transparent information, communication and modalities for the exercise of the rights', 'Data Subject Rights', 'high'],
            'Art.13' => ['Information to be provided where personal data are collected from the data subject', 'Data Subject Rights', 'high'],
            'Art.14' => ['Information to be provided where personal data have not been obtained from the data subject', 'Data Subject Rights', 'high'],
            'Art.15' => ['Right of access by the data subject', 'Data Subject Rights', 'high'],
            'Art.16' => ['Right to rectification', 'Data Subject Rights', 'high'],
            'Art.17' => ['Right to erasure (right to be forgotten)', 'Data Subject Rights', 'high'],
            'Art.18' => ['Right to restriction of processing', 'Data Subject Rights', 'medium'],
            'Art.19' => ['Notification obligation regarding rectification or erasure or restriction', 'Data Subject Rights', 'medium'],
            'Art.20' => ['Right to data portability', 'Data Subject Rights', 'medium'],
            'Art.21' => ['Right to object', 'Data Subject Rights', 'medium'],
            'Art.22' => ['Automated individual decision-making, including profiling', 'Data Subject Rights', 'high'],
            'Art.24' => ['Responsibility of the controller', 'Controller & Processor', 'high'],
            'Art.25' => ['Data protection by design and by default', 'Controller & Processor', 'critical'],
            'Art.26' => ['Joint controllers', 'Controller & Processor', 'medium'],
            'Art.27' => ['Representatives of controllers or processors not established in the Union', 'Controller & Processor', 'medium'],
            'Art.28' => ['Processor', 'Controller & Processor', 'high'],
            'Art.29' => ['Processing under the authority of the controller or processor', 'Controller & Processor', 'medium'],
            'Art.30' => ['Records of processing activities', 'Controller & Processor', 'high'],
            'Art.32' => ['Security of processing', 'Security', 'critical'],
            'Art.33' => ['Notification of a personal data breach to the supervisory authority', 'Security', 'critical'],
            'Art.34' => ['Communication of a personal data breach to the data subject', 'Security', 'critical'],
            'Art.35' => ['Data protection impact assessment', 'Security', 'high'],
            'Art.36' => ['Prior consultation', 'Security', 'medium'],
            'Art.37' => ['Designation of the data protection officer', 'DPO', 'medium'],
            'Art.38' => ['Position of the data protection officer', 'DPO', 'medium'],
            'Art.39' => ['Tasks of the data protection officer', 'DPO', 'medium'],
            'Art.44' => ['General principle for transfers', 'International Transfers', 'high'],
            'Art.45' => ['Transfers on the basis of an adequacy decision', 'International Transfers', 'medium'],
            'Art.46' => ['Transfers subject to appropriate safeguards', 'International Transfers', 'high'],
            'Art.47' => ['Binding corporate rules', 'International Transfers', 'medium'],
            'Art.49' => ['Derogations for specific situations', 'International Transfers', 'medium'],
        ];

        foreach ($articles as $ref => [$title, $domain, $sev]) {
            $controls[] = ['control_ref' => $ref, 'title' => $title, 'domain' => $domain, 'severity' => $sev, 'description' => "GDPR $ref: $title.", 'is_active' => true];
        }

        $this->upsertControls($f, $controls);
    }
}
