# AI Document Analysis Recommendations for SDCC Loan Web

## Executive Summary
Add AI-powered document analysis to enhance loan application processing by automatically extracting, validating, and scoring data from uploaded member documents (ID, proof of billing, income documents, etc.). This will improve approval accuracy, reduce fraud, and speed up decision-making.

## Current State Analysis
- Member applications: Upload ID picture + proof of billing
- Loan applications: Upload supporting documents via loan form
- Existing AI credit score: Simulated/random (70-99) converted to 300-850 range
- No document validation or data extraction currently implemented

## Recommended Approach: Phased Implementation

### Phase 1: Foundation & Quick Wins (0-2 weeks)
**Goal:** Add basic document validation without external AI costs

**Database Changes:**
```sql
-- Add to borrowers table
ALTER TABLE borrowers ADD COLUMN id_document_verified BOOLEAN DEFAULT FALSE;
ALTER TABLE borrowers ADD COLUMN id_expiry_date DATE NULL;
ALTER TABLE borrowers ADD COLUMN address_verified BOOLEAN DEFAULT FALSE;
ALTER TABLE borrowers ADD COLUMN document_extracted_data JSON NULL;
ALTER TABLE borrowers ADD COLUMN fraud_risk_score TINYINT UNSIGNED DEFAULT 0;
ALTER TABLE borrowers ADD COLUMN income_verification_status ENUM('unverified','partial','verified') DEFAULT 'unverified';

-- Add to loan_applications table
ALTER TABLE loan_applications ADD COLUMN document_processing_status ENUM('pending','processing','completed','failed') DEFAULT 'pending';
ALTER TABLE loan_applications ADD COLUMN ai_document_score TINYINT UNSIGNED NULL; -- 0-100 score from document analysis
```

**Implementable Today (No External APIs):**
1. **Enhanced Validation in LoanFormRequest.php:**
   - MIME type validation (not just extension)
   - Minimum image dimensions for ID photos (e.g., 600x400px)
   - File size limits per type
   - Name matching between ID and proof of billing (basic text comparison)

2. **Basic OCR with Tesseract (Free/Open Source):**
   ```php
   // In a document processing job/queue
   $text = shell_exec("tesseract {$filePath} stdout --psm 6");
   // Extract patterns: dates, ID numbers, names, addresses
   ```

3. **Document Validity Flags:**
   - ID expiry date validation
   - Address consistency check between documents
   - Document freshness (proof of billing < 3 months old)
   - Image quality assessment (blur, glare detection)

### Phase 2: External AI Service Integration (2-6 weeks)
**Goal:** Production-ready document understanding with high accuracy

**Recommended Service: Google Document AI**
- **Why:** Excellent for ID cards, forms, invoices; pre-trained models; Philippine language support; pay-as-you-go
- **Alternatives:** AWS Textract (if already on AWS), Azure Form Recognizer
- **Avoid:** Building custom models initially (requires 1000+ labeled documents)

**Implementation Plan:**
1. **Create DocumentProcessingService.php:**
   - Handles uploading to Google Cloud Storage
   - Calls Document AI processors (ID Card, Invoice, Form parsers)
   - Returns structured JSON data
   - Includes confidence scores

2. **Processors to Enable:**
   - `id_document`: Extracts ID number, name, DOB, expiry, address
   - `invoice`: For proof of billing (extracts biller, account, amount, date)
   - `form_parser`: Generic for payslips, bank statements, etc.

3. **Queue Integration:**
   - Process documents asynchronously via Laravel queues
   - Store results in `document_extracted_data` JSON field
   - Update validity/fraud scores based on extracted data

### Phase 3: Advanced Risk Modeling (6+ weeks)
**Goal:** Data-driven loan scoring using document-derived features

**Features to Extract & Model:**
1. **Identity Verification Score:**
   - ID validity (expiry, format)
   - Name match consistency across documents
   - Address verification (proof of billing matches ID)

2. **Income Stability Indicators:**
   - Average monthly income from payslips/bank statements
   - Income consistency (std dev over 3-6 months)
   - Employer verification (company name, tenure)

3. **Fraud Risk Signals:**
   - Document tampering detection (AI service confidence)
   - Inconsistent information across documents
   - Unusual document age/freshness patterns
   - Known fraudulent ID patterns

4. **Loan Capacity Enhancers:**
   - Residence stability (years at current address)
   - Banking relationship length (from statements)
   - Tax compliance (if ITRs submitted)

**Model Options:**
- **Start Simple:** Logistic regression with extracted features + existing credit score
- **Advanced:** Gradient boosting (XGBoost/LightGBM) or neural net if sufficient data
- **Continuous Learning:** Retrain monthly using approved/rejected loan outcomes

## Specific Technical Recommendations for SDCC

### 1. Database Schema Enhancements
```sql
-- Enhanced borrowers table
ALTER TABLE borrowers 
ADD COLUMN id_document_type VARCHAR(20) NULL, -- 'passport','driver','prc','etc'
ADD COLUMN id_number VARCHAR(50) NULL,
ADD COLUMN id_expiry_date DATE NULL,
ADD COLUMN id_issued_date DATE NULL,
ADD COLUMN id_issuing_authority VARCHAR(100) NULL,
ADD COLUMN full_name_extracted VARCHAR(100) NULL,
ADD COLUMN date_of_birth_extracted DATE NULL,
ADD COLUMN address_extracted TEXT NULL,
ADD COLUMN address_verified BOOLEAN DEFAULT FALSE,
ADD COLUMN id_confidence_score DECIMAL(3,2) NULL, -- 0.00-1.00 from AI
ADD COLUMN address_confidence_score DECIMAL(3,2) NULL,
ADD COLUMN income_verification_level TINYINT UNSIGNED DEFAULT 0, -- 0=none,1=declared,2=document-supported,3=bank-verified
ADD COLUMN monthly_income_verified DECIMAL(10,2) NULL,
ADD COLUMN income_source_confirmed VARCHAR(50) NULL, -- 'employed','self-employed','business'
ADD COLUMN employer_name_verified VARCHAR(100) NULL,
ADD COLUMN employment_start_date DATE NULL,
ADD COLUMN fraud_indicators JSON NULL, -- Array of flagged issues
ADD COLUMN document_processed_at TIMESTAMP NULL,
ADD COLUMN last_document_check TIMESTAMP NULL;

-- Enhanced loan_applications table
ALTER TABLE loan_applications
ADD COLUMN document_processing_status ENUM('pending','processing','completed','failed') DEFAULT 'pending',
ADD COLUMN ai_document_score TINYINT UNSIGNED NULL, -- 0-100 from document analysis
ADD COLUMN document_validation_passed BOOLEAN NULL,
ADD COLUMN extracted_income_average DECIMAL(10,2) NULL,
ADD COLUMN employment_verified BOOLEAN NULL,
ADD COLUMN residence_verified BOOLEAN NULL,
ADD COLUMN document_analysis_notes TEXT NULL;
```

### 2. Service Architecture
```
app/
├── Services/
│   ├── DocumentProcessingService.php          # Main orchestrator
│   ├── GoogleDocumentAIClient.php             # Wrapper for GCP API
│   ├── DocumentValidator.php                  # Validation rules
│   └── FraudDetector.php                      # Heuristics + ML
├── Jobs/
│   └── ProcessMemberDocument.php              # Queued job
├── Http/
│   └── Controllers/
│       └── DocumentController.php             # API endpoints for status
└── Libraries/
    └── OcrUtils.php                           # Tesseract wrapper (fallback)
```

### 3. Processing Flow
```
User Uploads Documents
        ↓
Laravel Validation (mime, size, dimensions)
        ↓
Store in Storage (temporary)
        ↓
Dispatch ProcessMemberDocument Job (queue)
        ↓
DocumentProcessingService:
   1. Upload to GCS bucket
   2. Call appropriate Document AI processor
   3. Parse response into structured data
   4. Run validation rules (name/address match, expiry, etc.)
   5. Calculate fraud risk score
   6. Update borrower/loan_application records
   7. Clean up temporary files
        ↓
Webhook/Notification to update UI
        ↓
Loan Officer sees enhanced data in review interface
```

### 4. Cost Estimation (Google Document AI)
- **ID Card Processor:** $6.00 per 1000 documents
- **Invoice/Form Parser:** $8.00 per 1000 documents
- **OCR-only:** $1.50 per 1000 pages
- **Sample Volume:** 100 loan apps/month × 2 docs each = 200 docs
- **Monthly Cost:** ~$16-30 (very affordable for started)
- **Free Tier:** $300/month for first year

### 5. Privacy & Security Considerations
- **Data Minimization:** Only extract needed fields
- **Encryption:** Store sensitive extracted data encrypted
- **Retention:** Auto-delete raw documents after processing (keep 7 days for audit)
- **Access Control:** Log all document access
- **Compliance:** Align with Philippine Data Privacy Act of 2012
- **Consent:** Add checkbox: "I authorize AI analysis of my documents for loan assessment"

### 6. Implementation Priorities
**Week 1-2:**
1. Add database schema changes
2. Implement basic Tesseract OCR fallback
3. Enhance validation rules in loan/member forms
4. Create document processing queue infrastructure

**Week 3-4:**
1. Set up Google Cloud project & Document AI
2. Implement GoogleDocumentAIClient service
3. Process ID cards and proof of billing
4. Add validity scoring to borrower model

**Week 5-6:**
1. Add income document processing (payslips, bank statements)
2. Build fraud detection heuristics
3. Create loan officer UI to show extracted/validated data
4. A/B test: compare approval rates with/without AI document score

### 7. Success Metrics to Track
- **Accuracy:** % of auto-extracted fields correct (sample audit)
- **Speed:** Avg time from upload to document-ready status
- **Fraud Reduction:** # of fraudulent apps caught pre-approval
- **Approval Quality:** Default rate of loans approved with AI doc score
- **User Satisfaction:** Loan officer feedback on document review time
- **Cost:** $ per processed document vs. manual review cost

## Risks & Mitigations

**Risk:** Poor OCR accuracy on low-quality phone photos
**Mitigation:** 
- Provide upload guidelines (good lighting, flat surface, no glare)
- Use AI services with low-quality image handling
- Fallback to manual review for low-confidence extractions

**Risk:** Privacy concerns with AI processing
**Mitigation:**
- Document data processing agreement with Google
- Store minimal necessary data
- Regular security audits
- Transparent privacy policy

**Risk:** Integration complexity
**Mitigation:**
- Start with one document type (ID cards)
- Use Laravel queues for loose coupling
- Comprehensive logging and monitoring
- Feature flags for gradual rollout

## Next Steps
1. Approve this recommendation document
2. Allocate 2-3 developer weeks for Phase 1
3. Set up Google Cloud trial account for testing
4. Design UI mockups for loan officer document review screen
5. Define specific validation rules for Philippine IDs/bills

This approach delivers immediate value while building toward a sophisticated AI-powered lending system that reduces risk, improves efficiency, and enhances member experience.