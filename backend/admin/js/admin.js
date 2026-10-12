/**
 * Play-On Mombasa - Admin Dashboard Interactive Scripts
 */

document.addEventListener('DOMContentLoaded', () => {
  // Auto-dismiss alert messages after 5 seconds
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach((alert) => {
    setTimeout(() => {
      alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
      alert.style.opacity = '0';
      alert.style.transform = 'translateY(-10px)';
      setTimeout(() => alert.remove(), 500);
    }, 5000);
  });

  // Modal helpers
  const viewModal = document.getElementById('viewModal');
  const editModal = document.getElementById('editModal');

  window.openModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add('is-active');
      document.body.style.overflow = 'hidden';
    }
  };

  window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove('is-active');
      document.body.style.overflow = '';
    }
  };

  // Close modal when clicking backdrop
  document.querySelectorAll('.admin-modal-backdrop').forEach((backdrop) => {
    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) {
        backdrop.classList.remove('is-active');
        document.body.style.overflow = '';
      }
    });
  });

  // Close on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.admin-modal-backdrop.is-active').forEach((modal) => {
        modal.classList.remove('is-active');
        document.body.style.overflow = '';
      });
    }
  });

  // Quick Status Change Form Submission
  const statusSelects = document.querySelectorAll('.quick-status-select');
  statusSelects.forEach((select) => {
    select.addEventListener('change', function() {
      const form = this.closest('form');
      if (form) {
        if (confirm(`Change booking status to "${this.value.toUpperCase()}"?`)) {
          form.submit();
        } else {
          // Revert select to original value
          this.value = this.dataset.current;
        }
      }
    });
  });
});

/**
 * Open Booking Details Modal & Populate Content
 */
function viewBookingDetails(b) {
  const body = document.getElementById('viewModalBody');
  if (!body) return;

  const notesHtml = b.notes ? b.notes.replace(/\n/g, '<br>') : '<em>No customer notes</em>';
  const adminNotesHtml = b.admin_notes ? b.admin_notes.replace(/\n/g, '<br>') : '<em>No staff notes</em>';

  let statusBadge = `<span class="badge-status ${b.status}">${b.status}</span>`;

  body.innerHTML = `
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; border-bottom:1px solid #E2EFE7; padding-bottom:0.75rem;">
      <div>
        <h3 style="font-family:var(--font-heading); color:var(--brand-green-deep); margin-bottom:2px;">Booking ${b.booking_ref}</h3>
        <span style="font-size:0.85rem; color:var(--text-muted);">Created on ${b.created_at}</span>
      </div>
      <div>${statusBadge}</div>
    </div>

    <table style="width:100%; border-collapse:collapse; font-size:0.95rem; margin-bottom:1.5rem;">
      <tr style="border-bottom:1px solid #EAEFEA;">
        <td style="padding:8px 0; font-weight:700; color:var(--text-muted); width:35%;">Parent / Guardian:</td>
        <td style="padding:8px 0; font-weight:800; color:var(--text-dark);">${b.full_name}</td>
      </tr>
      <tr style="border-bottom:1px solid #EAEFEA;">
        <td style="padding:8px 0; font-weight:700; color:var(--text-muted);">Phone Number:</td>
        <td style="padding:8px 0;"><a href="https://wa.me/${b.phone.replace(/[^0-9]/g, '')}" target="_blank" style="color:var(--brand-green); font-weight:700;"><i class="fab fa-whatsapp"></i> ${b.phone}</a></td>
      </tr>
      <tr style="border-bottom:1px solid #EAEFEA;">
        <td style="padding:8px 0; font-weight:700; color:var(--text-muted);">Email Address:</td>
        <td style="padding:8px 0;"><a href="mailto:${b.email}" style="color:var(--brand-green);">${b.email}</a></td>
      </tr>
      <tr style="border-bottom:1px solid #EAEFEA;">
        <td style="padding:8px 0; font-weight:700; color:var(--text-muted);">Package / Type:</td>
        <td style="padding:8px 0; font-weight:700; color:var(--brand-green-deep);">${b.booking_type_label || b.booking_type}</td>
      </tr>
      <tr style="border-bottom:1px solid #EAEFEA;">
        <td style="padding:8px 0; font-weight:700; color:var(--text-muted);">Visit Date:</td>
        <td style="padding:8px 0; font-weight:800; color:var(--brand-orange);">${b.booking_date}</td>
      </tr>
      <tr style="border-bottom:1px solid #EAEFEA;">
        <td style="padding:8px 0; font-weight:700; color:var(--text-muted);">Session Slot:</td>
        <td style="padding:8px 0; font-weight:700;">${b.session_time}</td>
      </tr>
      <tr style="border-bottom:1px solid #EAEFEA;">
        <td style="padding:8px 0; font-weight:700; color:var(--text-muted);">Guests Breakdown:</td>
        <td style="padding:8px 0;"><strong>${b.children_count}</strong> Child(ren), <strong>${b.adults_count}</strong> Adult(s)</td>
      </tr>
      <tr style="border-bottom:1px solid #EAEFEA;">
        <td style="padding:8px 0; font-weight:700; color:var(--text-muted);">Estimated Pass Total:</td>
        <td style="padding:8px 0; font-size:1.15rem; font-weight:800; color:var(--brand-green);">${b.estimated_total}</td>
      </tr>
      <tr style="border-bottom:1px solid #EAEFEA;">
        <td style="padding:8px 0; font-weight:700; color:var(--text-muted); vertical-align:top;">Customer Requests:</td>
        <td style="padding:8px 0; line-height:1.4;">${notesHtml}</td>
      </tr>
      <tr>
        <td style="padding:8px 0; font-weight:700; color:var(--text-muted); vertical-align:top;">Staff / Internal Notes:</td>
        <td style="padding:8px 0; line-height:1.4; color:#5A4300;">${adminNotesHtml}</td>
      </tr>
    </table>

    <div style="display:flex; gap:0.5rem; justify-content:flex-end;">
      <a href="https://wa.me/${b.phone.replace(/[^0-9]/g, '')}?text=Hi%20${encodeURIComponent(b.full_name)}!%20Regarding%20your%20Play-On%20booking%20reservation%20(${b.booking_ref}):" target="_blank" class="btn btn-sm btn-primary">
        <i class="fab fa-whatsapp"></i> Chat on WhatsApp
      </a>
      <button type="button" onclick="window.print()" class="btn btn-sm btn-secondary">
        <i class="fas fa-print"></i> Print Details
      </button>
    </div>
  `;

  openModal('viewModal');
}

/**
 * Open Edit Booking Modal & Populate Fields
 */
function editBooking(b) {
  document.getElementById('editBookingId').value = b.id;
  document.getElementById('editFullName').value = b.full_name;
  document.getElementById('editPhone').value = b.phone;
  document.getElementById('editEmail').value = b.email;
  document.getElementById('editBookingDate').value = b.booking_date;
  document.getElementById('editSessionTime').value = b.session_time;
  document.getElementById('editChildrenCount').value = b.children_count;
  document.getElementById('editAdultsCount').value = b.adults_count;
  document.getElementById('editEstimatedTotal').value = b.estimated_total;
  document.getElementById('editStatus').value = b.status;
  document.getElementById('editNotes').value = b.notes || '';
  document.getElementById('editAdminNotes').value = b.admin_notes || '';

  openModal('editModal');
}
