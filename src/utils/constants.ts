export const GENDERS = ['Male', 'Female', 'Other'] as const;

export const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as const;

export const LEAD_SOURCES = [
  'Website', 'Phone', 'Walk-in', 'WhatsApp', 'Facebook', 'Instagram',
  'Google Ads', 'Referral', 'Existing Patient', 'Other',
] as const;

export const LEAD_STATUSES = [
  'New', 'Contacted', 'Follow-up', 'Interested', 'Appointment Booked',
  'Converted', 'Not Interested', 'Lost',
] as const;

export const APPOINTMENT_STATUSES = [
  'Scheduled', 'Confirmed', 'Checked In', 'Waiting', 'In Consultation',
  'Completed', 'Cancelled', 'No Show', 'Rescheduled',
] as const;

export const INVOICE_STATUSES = [
  'Draft', 'Unpaid', 'Partially Paid', 'Paid', 'Cancelled', 'Refunded',
] as const;

export const PAYMENT_METHODS = ['Cash', 'UPI', 'Card', 'Bank Transfer', 'Online', 'Other'] as const;

export const EXPENSE_STATUSES = ['Draft', 'Pending', 'Approved', 'Rejected', 'Paid'] as const;

export const PRIORITIES = ['Low', 'Medium', 'High', 'Urgent'] as const;

export const PER_PAGE_OPTIONS = [10, 25, 50, 100] as const;
