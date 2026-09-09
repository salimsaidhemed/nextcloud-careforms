(function () {
    'use strict';

    window.CareForms = window.CareForms || {};
    window.CareForms.formDefinitions = window.CareForms.formDefinitions || {};

    window.CareForms.formDefinitions.nursesProgressNote = {
        id: 'nurses-progress-note',
        name: 'Nurses Progress Note',
        category: 'Nursing',
        version: 1,
        description: 'Comprehensive nursing visit assessment and progress documentation.',
        fields: [
            { id: 'patient-information', type: 'section', label: 'Patient Information', fields: [
                { id: 'patient_name', type: 'text', label: 'Patient Name', required: true },
                { id: 'mr_number', type: 'text', label: 'MR#' },
                { id: 'diagnosis', type: 'text', label: 'Diagnosis' },
                { id: 'living_arrangement', type: 'choice-group', label: 'Living Arrangements', options: ['Alone', 'Family', 'Other'] },
                { id: 'living_other', type: 'text', label: 'Living Arrangement - Other / Specify' },
                { id: 'visit_type', type: 'choice-group', label: 'Visit Type', options: ['Skilled (High/Low)', '2-Week Supervisory', '60 Day Recertification', 'Post Hospitalization', 'Discharge'] }
            ]},
            { id: 'vital-signs', type: 'section', label: 'Vital Signs', fields: [
                { id: 'bp_lying', type: 'text', label: 'BP Lying' }, { id: 'bp_sitting', type: 'text', label: 'BP Sitting' },
                { id: 'bp_standing', type: 'text', label: 'BP Standing' }, { id: 'bp_arm', type: 'choice-group', label: 'BP Arm', options: ['Right', 'Left'] },
                { id: 'temperature', type: 'text', label: 'Temperature' }, { id: 'pulse_apical', type: 'text', label: 'Pulse - Apical' },
                { id: 'pulse_apical_rhythm', type: 'choice-group', label: 'Apical Rhythm', options: ['Regular', 'Irregular'] },
                { id: 'pulse_radial', type: 'text', label: 'Pulse - Radial' }, { id: 'pulse_radial_rhythm', type: 'choice-group', label: 'Radial Rhythm', options: ['Regular', 'Irregular'] },
                { id: 'respirations', type: 'text', label: 'Respirations' }, { id: 'resp_rhythm', type: 'choice-group', label: 'Respiratory Rhythm', options: ['Regular', 'Irregular'] },
                { id: 'diet', type: 'checkbox-group', label: 'Diet', options: ['Calories', 'ADA', 'Low Cholesterol', 'Low Salt', 'Other'] },
                { id: 'diet_other', type: 'text', label: 'Diet - Other / Specify' }
            ]},
            { id: 'pain', type: 'section', label: 'Pain Assessment', fields: [
                { id: 'pain_present', type: 'choice-group', label: 'Pain Present', options: ['Yes', 'No'] },
                { id: 'pain_location', type: 'text', label: 'Location' }, { id: 'pain_intensity', type: 'number', label: 'Intensity (1-10)', min: 1, max: 10 },
                { id: 'pain_management', type: 'textarea', label: 'Pain Management', rows: 3 }
            ]},
            { id: 'sensory', type: 'section', label: 'Sensory', fields: [
                { id: 'eyes', type: 'checkbox-group', label: 'Eyes', options: ['Glasses', 'Blurred Vision', 'Glaucoma'] },
                { id: 'ears', type: 'checkbox-group', label: 'Ears', options: ['Hard of Hearing', 'Deaf', 'Hearing Aid Right', 'Hearing Aid Left', 'Ringing'] },
                { id: 'dentures', type: 'choice-group', label: 'Dentures', options: ['Yes', 'No', 'Other'] }, { id: 'sensory_other', type: 'text', label: 'Other / Specify' }
            ]},
            { id: 'neurological', type: 'section', label: 'Neurological', fields: [
                { id: 'neuro_findings', type: 'checkbox-group', label: 'Findings', options: ['Headache', 'Dizziness', 'Vertigo', 'Syncope', 'Seizure', 'Hemiparesis Right', 'Hemiparesis Left', 'Dysphagia', 'Aphasia', 'Numbness', 'Tremors', 'Tingling', 'Grasp Right', 'Grasp Left'] }
            ]},
            { id: 'respiratory', type: 'section', label: 'Respiratory', fields: [
                { id: 'lung_sounds', type: 'checkbox-group', label: 'Lung Sounds', options: ['Clear', 'Diminished', 'Rales', 'Rhonchi', 'Wheeze'] },
                { id: 'sob', type: 'checkbox-group', label: 'Shortness of Breath', options: ['Talking', 'Rest', 'Dressing', 'Walking'] },
                { id: 'cough', type: 'choice-group', label: 'Cough', options: ['Productive', 'Nonproductive', 'None'] },
                { id: 'sputum', type: 'text', label: 'Sputum' }, { id: 'oxygen', type: 'text', label: 'O2' },
                { id: 'resp_support', type: 'checkbox-group', label: 'Support / Other Findings', options: ['Nebulizer', 'Orthopnea', 'Other'] }, { id: 'resp_other', type: 'text', label: 'Other / Specify' }
            ]},
            { id: 'cardiac', type: 'section', label: 'Cardiac', fields: [
                { id: 'cardiac_findings', type: 'checkbox-group', label: 'Heart / Cardiac Findings', options: ['WNL', 'Irregular', 'Chest Pain', 'Palpitations', 'Pacemaker'] },
                { id: 'cardiac_other', type: 'text', label: 'Other' }, { id: 'cardiac_frequency', type: 'text', label: 'Frequency' }, { id: 'cardiac_relief', type: 'text', label: 'Relief' }
            ]},
            { id: 'circulation', type: 'section', label: 'Peripheral / Circulation', fields: [
                { id: 'extremity_color', type: 'text', label: 'Extremity Color' }, { id: 'extremity_temperature', type: 'text', label: 'Extremity Temperature' },
                { id: 'peripheral_pulses', type: 'text', label: 'Peripheral Pulses' }, { id: 'circulation', type: 'checkbox-group', label: 'Circulation', options: ['WNL', 'Edema', 'Pitting', 'Non-pitting'] },
                { id: 'edema_location', type: 'text', label: 'Edema Location' }, { id: 'edema_grade', type: 'choice-group', label: 'Edema Grade', options: ['1+', '2+', '3+', '4+'] }
            ]},
            { id: 'genitourinary', type: 'section', label: 'Genitourinary', fields: [
                { id: 'gu_findings', type: 'checkbox-group', label: 'GU Findings', options: ['WNL', 'Urine Clear', 'Urine Cloudy', 'Sediment', 'Nocturia', 'Hematuria', 'Polyuria', 'Oliguria'] },
                { id: 'urinary_incontinence', type: 'choice-group', label: 'Urinary Incontinence', options: ['Yes', 'No'] },
                { id: 'catheter_type', type: 'choice-group', label: 'Catheter', options: ['None', 'Indwelling', 'Suprapubic'] }, { id: 'catheter_size', type: 'text', label: 'Catheter Size' },
                { id: 'last_catheter_change', type: 'date', label: 'Last Catheter Change' }
            ]},
            { id: 'gastrointestinal', type: 'section', label: 'Gastrointestinal', fields: [
                { id: 'gi_findings', type: 'checkbox-group', label: 'GI Findings', options: ['WNL', 'Constipation', 'Diarrhea', 'Nausea/Vomiting', 'Bowel Incontinence', 'NG/GT', 'Ostomy', 'Abdominal Pain', 'Tenderness', 'Distention', 'Ascites'] },
                { id: 'last_bm', type: 'text', label: 'Last BM' }, { id: 'bowel_sounds', type: 'choice-group', label: 'Bowel Sounds', options: ['Present', 'Absent'] },
                { id: 'appetite_change', type: 'choice-group', label: 'Appetite Change', options: ['Yes', 'No'] }
            ]},
            { id: 'endocrine', type: 'section', label: 'Endocrine', fields: [
                { id: 'endocrine_status', type: 'choice-group', label: 'Endocrine Status', options: ['WNL', 'Abnormal'] }, { id: 'endocrine_abnormal', type: 'text', label: 'Abnormal Findings' },
                { id: 'blood_sugar', type: 'text', label: 'Blood Sugar' }, { id: 'blood_sugar_timing', type: 'choice-group', label: 'Blood Sugar', options: ['Fasting', 'Non-fasting'] },
                { id: 'tested_by', type: 'choice-group', label: 'Tested By', options: ['Patient', 'RN/LPN', 'Other'] },
                { id: 'endocrine_findings', type: 'checkbox-group', label: 'Additional Findings', options: ['Enlarged Thyroid', 'Decreased Thyroid Function', 'Sweating'] }
            ]},
            { id: 'skin', type: 'section', label: 'Skin Integrity', fields: [
                { id: 'skin_integrity', type: 'choice-group', label: 'Skin Integrity', options: ['Intact', 'Compromised'] }, { id: 'skin_color', type: 'text', label: 'Color' }, { id: 'skin_temperature', type: 'text', label: 'Temperature' },
                { id: 'skin_findings', type: 'checkbox-group', label: 'Findings', options: ['Scars', 'Lesions', 'Bruises', 'Dry', 'Itching', 'Rash', 'Petechiae'] },
                { id: 'skin_description', type: 'textarea', label: 'Description / Other', rows: 3 }
            ]},
            { id: 'wound', type: 'section', label: 'Wound Evaluation', fields: [
                { id: 'wound_location', type: 'text', label: 'Location' }, { id: 'wound_length', type: 'number', label: 'Length (cm)', min: 0 }, { id: 'wound_width', type: 'number', label: 'Width (cm)', min: 0 }, { id: 'wound_depth', type: 'number', label: 'Depth (cm)', min: 0 },
                { id: 'wound_color', type: 'text', label: 'Color' }, { id: 'wound_drainage', type: 'text', label: 'Drainage' }, { id: 'wound_odor', type: 'text', label: 'Odor' },
                { id: 'wound_surrounding', type: 'text', label: 'Surrounding Area' }, { id: 'wound_care', type: 'textarea', label: 'Wound Care Provided', rows: 3 }
            ]},
            { id: 'mental-status', type: 'section', label: 'Mental Status', fields: [
                { id: 'mental_status', type: 'checkbox-group', label: 'Observed Status', options: ['Alert', 'Oriented', 'Forgetful', 'Confused', 'Sad', 'Anxious', 'Agitated', 'Depressed', 'Lethargic', 'Other'] }, { id: 'mental_other', type: 'text', label: 'Other / Specify' }
            ]},
            { id: 'functional-status', type: 'section', label: 'Functional Status', fields: [
                { id: 'functional_status', type: 'checkbox-group', label: 'Functional Status', options: ['Independent', 'Dependent', 'Assistance', 'Supervision', 'Amputation', 'Contracture', 'Paralysis', 'Other'] },
                { id: 'assistive_devices', type: 'checkbox-group', label: 'Assistive Devices', options: ['Cane', 'Wall/Furniture', 'Walker', 'Electrical Scooter', 'Wheelchair', 'Other'] }, { id: 'functional_other', type: 'text', label: 'Other / Specify' }
            ]},
            { id: 'homebound', type: 'section', label: 'Homebound Status', fields: [
                { id: 'homebound_reasons', type: 'checkbox-group', label: 'Homebound Reasons', options: ['Taxing Effort', 'Restricted Mobility', 'Other'] }, { id: 'homebound_other', type: 'textarea', label: 'Details / Other', rows: 3 }
            ]},
            { id: 'skilled-nursing', type: 'section', label: 'Skilled Nursing', fields: [
                { id: 'skilled_nursing_narrative', type: 'textarea', label: 'Skilled Nursing Narrative', rows: 5 }, { id: 'continue_skilled_nursing', type: 'choice-group', label: 'Continue Skilled Nursing', options: ['Yes', 'No'] }
            ]},
            { id: 'response-intervention', type: 'section', label: 'Response to Intervention', fields: [
                { id: 'response_to_intervention', type: 'textarea', label: 'Response to Intervention', rows: 4 }
            ]},
            { id: 'education', type: 'section', label: 'Patient / Caregiver Understanding', fields: [
                { id: 'understanding', type: 'choice-group', label: 'Understanding', options: ['Verbalized Understanding', 'Needs Further Teaching', 'Unable'] }
            ]},
            { id: 'md-contact', type: 'section', label: 'MD Contact / Orders', fields: [
                { id: 'md_contact', type: 'textarea', label: 'MD Contact', rows: 3 }, { id: 'medication_changes', type: 'choice-group', label: 'Medication Changes', options: ['Yes', 'No'] },
                { id: 'medication_changes_details', type: 'textarea', label: 'Medication Change Details', rows: 3 }, { id: 'new_orders', type: 'choice-group', label: 'New Orders', options: ['Yes', 'No'] },
                { id: 'new_orders_details', type: 'textarea', label: 'New Order Details', rows: 3 }, { id: 'next_md_appointment', type: 'text', label: 'Next MD Appointment' }
            ]},
            { id: 'planning', type: 'section', label: 'Discharge Planning / Progress Towards Goals', fields: [
                { id: 'discharge_planning', type: 'textarea', label: 'Discharge Planning / Progress Towards Goals', rows: 4 },
                { id: 'patient_feedback', type: 'textarea', label: 'Patient / Significant Other Feedback', rows: 4 }
            ]},
            { id: 'narrative', type: 'section', label: 'Narrative', fields: [
                { id: 'narrative', type: 'textarea', label: 'Narrative', rows: 8 }
            ]},
            { id: 'signature', type: 'section', label: 'Nurse Signature', fields: [
                { id: 'nurse_name', type: 'text', label: 'RN/LPN Name', required: true }, { id: 'signature', type: 'signature-placeholder', label: 'Signature' }, { id: 'signature_date', type: 'date', label: 'Date' }
            ]}
        ]
    };
}());