(function () {
    'use strict';

    window.CareForms = window.CareForms || {};

    window.CareForms.formDefinitions = window.CareForms.formDefinitions || {};

    window.CareForms.formDefinitions.homeHealthAide = {
        id: 'home-health-aide-note',
        name: 'Home Health Aide Note',
        category: 'Home Health',
        version: 1,
        description: 'Visit documentation for home health aide services.',
        fields: [
            {
                id: 'patient-information',
                type: 'section',
                label: 'Patient Information',
                fields: [
                    { id: 'patient_name', type: 'text', label: 'Patient Name', required: true },
                    { id: 'visit_date', type: 'date', label: 'Visit Date', required: true }
                ]
            },
            {
                id: 'personal-care',
                type: 'section',
                label: 'Personal Care',
                fields: [
                    {
                        id: 'personal_care',
                        type: 'checkbox-group',
                        label: 'Services Provided',
                        options: [
                            'Bed Bath', 'Tub Bath', 'Chair Bath', 'Shower', 'Sponge Bath',
                            'Shampoo', 'Groom Hair', 'Oral Care', 'Nails'
                        ]
                    }
                ]
            },
            {
                id: 'nutrition',
                type: 'section',
                label: 'Nutrition',
                fields: [
                    { id: 'appetite', type: 'text', label: 'Appetite' },
                    { id: 'meal_eaten_percent', type: 'number', label: '% Meal Eaten', min: 0, max: 100 },
                    {
                        id: 'nutrition_tasks',
                        type: 'checkbox-group',
                        label: 'Nutrition Support',
                        options: ['Fluids Adequate', 'Prepare Meals', 'Serve Meals', 'Feed Patient']
                    }
                ]
            },
            {
                id: 'mental-status',
                type: 'section',
                label: 'Mental Status',
                fields: [
                    {
                        id: 'mental_status',
                        type: 'checkbox-group',
                        label: 'Observed Status',
                        options: [
                            'Alert', 'Lethargic', 'Confused', 'Disoriented', 'Depressed',
                            'Hostile', 'Forgetful', 'Tearful', 'Cooperative', 'Combative'
                        ]
                    }
                ]
            },
            {
                id: 'elimination',
                type: 'section',
                label: 'Elimination',
                fields: [
                    { id: 'bowel_movement', type: 'checkbox', label: 'Bowel Movement' },
                    { id: 'last_bowel_movement_date', type: 'date', label: 'Last Bowel Movement Date' },
                    {
                        id: 'elimination_observations',
                        type: 'checkbox-group',
                        label: 'Elimination Care / Observations',
                        options: [
                            'Voiding', 'Incontinent Urine', 'Incontinent Stool',
                            'Empty Drainage Bag', 'Catheter Care', 'Condom Catheter Care'
                        ]
                    },
                    { id: 'drainage_amount', type: 'text', label: 'Drainage Amount' }
                ]
            },
            {
                id: 'activity',
                type: 'section',
                label: 'Activity',
                fields: [
                    {
                        id: 'activity',
                        type: 'checkbox-group',
                        label: 'Activity',
                        options: ['Complete Bed Rest', 'Bathroom Only', 'Assist Ambulation', 'Passive ROM']
                    }
                ]
            },
            {
                id: 'assistive-device',
                type: 'section',
                label: 'Assistive Device',
                fields: [
                    {
                        id: 'assistive_device',
                        type: 'checkbox-group',
                        label: 'Device Used',
                        options: ['Wheelchair', 'Walker', 'Crutches', 'Straight Cane', 'Quad Cane', 'Scooter']
                    }
                ]
            },
            {
                id: 'housekeeping',
                type: 'section',
                label: 'Housekeeping',
                fields: [
                    {
                        id: 'housekeeping',
                        type: 'checkbox-group',
                        label: 'Tasks Completed',
                        options: ['Light Housekeeping', "Patient's Laundry", 'Shopping', 'Dishes']
                    }
                ]
            },
            {
                id: 'notes',
                type: 'section',
                label: 'Additional Notes / Comments',
                fields: [
                    { id: 'additional_notes', type: 'textarea', label: 'Additional Notes / Comments', rows: 5 }
                ]
            },
            {
                id: 'visit-details',
                type: 'section',
                label: 'Home Health Aide Visit Details',
                fields: [
                    { id: 'aide_name', type: 'text', label: 'Aide Name', required: true },
                    { id: 'signature', type: 'signature-placeholder', label: 'Signature' },
                    { id: 'signature_date', type: 'date', label: 'Signature Date' },
                    { id: 'time_in', type: 'time', label: 'Time In' },
                    { id: 'time_out', type: 'time', label: 'Time Out' }
                ]
            }
        ]
    };
}());