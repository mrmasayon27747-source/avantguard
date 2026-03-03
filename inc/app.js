document.addEventListener("DOMContentLoaded", () => {
  // Logout dropdown (existing)
  const btn = document.getElementById("logoutBtn");
  const menu = document.getElementById("logoutMenu");

  if (btn && menu) {
    btn.addEventListener("click", (e) => {
      e.stopPropagation();
      menu.hidden = !menu.hidden;
    });
    document.addEventListener("click", () => (menu.hidden = true));
  }

  // Theme toggle (works for sidebar + navbar buttons)
  const applyTheme = (theme) => {
    document.documentElement.setAttribute("data-theme", theme);
    try { localStorage.setItem("theme", theme); } catch (e) {}
  };

  const getTheme = () => {
    try { return localStorage.getItem("theme") || "dark"; } catch (e) { return "dark"; }
  };

  // Apply saved theme on page load
  applyTheme(getTheme());

  document.querySelectorAll("[data-theme-toggle]").forEach((el) => {
    el.addEventListener("click", () => {
      const cur = getTheme();
      applyTheme(cur === "dark" ? "light" : "dark");
    });
  });

  // Mobile sidebar toggle
  const sidebar = document.querySelector(".sidebar");
  const sidebarToggle = document.querySelector(".sidebar-toggle");
  const sidebarOverlay = document.querySelector(".sidebar-overlay");

  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener("click", () => {
      sidebar.classList.toggle("open");
      if (sidebarOverlay) {
        sidebarOverlay.classList.toggle("visible");
      }
    });
  }

  if (sidebarOverlay && sidebar) {
    sidebarOverlay.addEventListener("click", () => {
      sidebar.classList.remove("open");
      sidebarOverlay.classList.remove("visible");
    });
  }

  // Animated counters (for dashboard KPIs)
  const animateCount = (el) => {
    const target = Number(el.getAttribute("data-count") || "0");
    const duration = Number(el.getAttribute("data-duration") || "900");
    const start = 0;
    const t0 = performance.now();

    const step = (t) => {
      const p = Math.min(1, (t - t0) / duration);
      const val = Math.round(start + (target - start) * (1 - Math.pow(1 - p, 3)));
      el.textContent = val.toLocaleString();
      if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  };

  document.querySelectorAll("[data-count]").forEach(animateCount);

  // Sidebar dropdown toggles
  document.querySelectorAll('.nav-dropdown-toggle').forEach(toggle => {
    toggle.addEventListener('click', (e) => {
      e.preventDefault();
      const dropdown = toggle.closest('.nav-dropdown');
      if (dropdown) {
        dropdown.classList.toggle('open');
      }
    });
  });

  // Custom Time Picker
  initTimePickers();

  // Flatpickr for all date inputs - provides easy year/month navigation
  initFlatpickrDates();
});

// Time Picker Initialization
function initTimePickers() {
  document.querySelectorAll('.time-picker-trigger').forEach(trigger => {
    const wrapper = trigger.closest('.time-picker-wrapper');
    const dropdown = wrapper.querySelector('.time-picker-dropdown');
    const hiddenInput = wrapper.querySelector('input[type="hidden"]');
    const textInput = trigger.querySelector('.time-text-input');
    
    const hourSelect = dropdown.querySelector('.tp-hour');
    const minuteSelect = dropdown.querySelector('.tp-minute');
    const amBtn = dropdown.querySelector('.tp-am');
    const pmBtn = dropdown.querySelector('.tp-pm');
    const confirmBtn = dropdown.querySelector('.tp-confirm');
    const cancelBtn = dropdown.querySelector('.tp-cancel');
    
    // Move dropdown to body for proper z-index
    document.body.appendChild(dropdown);
    dropdown.style.position = 'fixed';
    
    function positionDropdown() {
      const rect = trigger.getBoundingClientRect();
      dropdown.style.top = (rect.bottom + 4) + 'px';
      dropdown.style.left = rect.left + 'px';
    }
    
    // Text input - sync to hidden input on change
    textInput.addEventListener('input', () => {
      hiddenInput.value = textInput.value;
    });
    
    textInput.addEventListener('blur', () => {
      hiddenInput.value = textInput.value;
    });
    
    // Click on SVG icon opens dropdown
    const svgIcon = trigger.querySelector('svg');
    svgIcon.style.cursor = 'pointer';
    svgIcon.addEventListener('click', (e) => {
      e.stopPropagation();
      document.querySelectorAll('.time-picker-dropdown.open, .date-picker-dropdown.open').forEach(d => {
        if (d !== dropdown) d.classList.remove('open');
      });
      positionDropdown();
      dropdown.classList.toggle('open');
    });
    
    // AM/PM toggle
    amBtn.addEventListener('click', () => {
      amBtn.classList.add('active');
      pmBtn.classList.remove('active');
    });
    
    pmBtn.addEventListener('click', () => {
      pmBtn.classList.add('active');
      amBtn.classList.remove('active');
    });
    
    // Confirm selection
    confirmBtn.addEventListener('click', () => {
      const hour = hourSelect.value;
      const minute = minuteSelect.value;
      const ampm = amBtn.classList.contains('active') ? 'AM' : 'PM';
      
      const displayValue = `${hour}:${minute} ${ampm}`;
      textInput.value = displayValue;
      hiddenInput.value = displayValue;
      dropdown.classList.remove('open');
    });
    
    // Cancel
    cancelBtn.addEventListener('click', () => {
      dropdown.classList.remove('open');
    });
  });
  
  // Close dropdowns when clicking outside
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.time-picker-wrapper') && !e.target.closest('.time-picker-dropdown')) {
      document.querySelectorAll('.time-picker-dropdown.open').forEach(d => {
        d.classList.remove('open');
      });
    }
  });
}

// Helper to create time picker HTML
function createTimePickerHTML(name, placeholder, value = '', required = false) {
  // Parse existing value
  let hour = '8', minute = '00', ampm = 'AM';
  if (value) {
    const match = value.match(/(\d{1,2}):(\d{2})\s*(AM|PM|am|pm)?/i);
    if (match) {
      hour = match[1];
      minute = match[2];
      ampm = (match[3] || 'AM').toUpperCase();
    }
  }
  
  const hourOptions = Array.from({length: 12}, (_, i) => {
    const h = i + 1;
    return `<option value="${h}" ${h == hour ? 'selected' : ''}>${h}</option>`;
  }).join('');
  
  const minuteOptions = Array.from({length: 60}, (_, i) => {
    const m = String(i).padStart(2, '0');
    return `<option value="${m}" ${m === minute ? 'selected' : ''}>${m}</option>`;
  }).join('');
  
  return `
    <div class="time-picker-wrapper">
      <input type="hidden" name="${name}" value="${value}" ${required ? 'required' : ''}>
      <div class="time-picker-display time-picker-trigger">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <polyline points="12,6 12,12 16,14"/>
        </svg>
        <input type="text" class="time-text-input" value="${value || ''}" placeholder="${placeholder}">
      </div>
      <div class="time-picker-dropdown">
        <div class="time-picker-row">
          <select class="time-picker-select tp-hour">${hourOptions}</select>
          <span class="time-picker-separator">:</span>
          <select class="time-picker-select tp-minute">${minuteOptions}</select>
          <div class="time-picker-ampm">
            <button type="button" class="tp-am ${ampm === 'AM' ? 'active' : ''}">AM</button>
            <button type="button" class="tp-pm ${ampm === 'PM' ? 'active' : ''}">PM</button>
          </div>
        </div>
        <div class="time-picker-actions">
          <button type="button" class="tp-cancel">Cancel</button>
          <button type="button" class="tp-confirm">OK</button>
        </div>
      </div>
    </div>
  `;
}

// Auto-convert text inputs with class 'time-picker-input' to custom time pickers
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('input.time-picker-input').forEach(input => {
    const wrapper = document.createElement('div');
    wrapper.innerHTML = createTimePickerHTML(
      input.name,
      input.placeholder || 'Select time',
      input.value,
      input.required
    );
    input.parentNode.replaceChild(wrapper.firstElementChild, input);
  });
  
  // Re-init after conversion
  setTimeout(initTimePickers, 0);
  
  // Initialize custom date pickers
  initCustomDatePickers();
});

// Custom Date Picker
function initCustomDatePickers() {
  document.querySelectorAll('input[type="date"]').forEach(input => {
    // Skip if already initialized
    if (input.dataset.datePickerInit) return;
    input.dataset.datePickerInit = 'true';
    
    // Create wrapper
    const wrapper = document.createElement('div');
    wrapper.className = 'date-picker-wrapper';
    
    // Create display element
    const display = document.createElement('div');
    display.className = 'date-picker-display';
    display.innerHTML = `
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
        <line x1="16" y1="2" x2="16" y2="6"/>
        <line x1="8" y1="2" x2="8" y2="6"/>
        <line x1="3" y1="10" x2="21" y2="10"/>
      </svg>
      <span class="date-display-text">${input.value ? formatDateDisplay(input.value) : 'Select date'}</span>
    `;
    
    // Create calendar dropdown - append to body for proper z-index
    const dropdown = document.createElement('div');
    dropdown.className = 'date-picker-dropdown';
    dropdown.style.position = 'fixed';
    document.body.appendChild(dropdown);
    
    // Insert wrapper
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(display);
    wrapper.appendChild(input);
    input.style.display = 'none';
    
    let currentDate = input.value ? new Date(input.value + 'T00:00:00') : new Date();
    let viewDate = new Date(currentDate);
    
    function positionDropdown() {
      const rect = display.getBoundingClientRect();
      dropdown.style.top = (rect.bottom + 4) + 'px';
      dropdown.style.left = rect.left + 'px';
    }
    
    display.addEventListener('click', (e) => {
      e.stopPropagation();
      document.querySelectorAll('.date-picker-dropdown.open, .time-picker-dropdown.open').forEach(d => {
        if (d !== dropdown) d.classList.remove('open');
      });
      positionDropdown();
      dropdown.classList.toggle('open');
      if (dropdown.classList.contains('open')) {
        renderCalendar();
      }
    });
    
    function renderCalendar() {
      const months = ['January', 'February', 'March', 'April', 'May', 'June', 
                      'July', 'August', 'September', 'October', 'November', 'December'];
      const year = viewDate.getFullYear();
      const month = viewDate.getMonth();
      
      const firstDay = new Date(year, month, 1).getDay();
      const daysInMonth = new Date(year, month + 1, 0).getDate();
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      
      let calendarHTML = `
        <div class="dp-header">
          <button type="button" class="dp-nav dp-prev">&lt;</button>
          <span class="dp-title">${months[month]} ${year}</span>
          <button type="button" class="dp-nav dp-next">&gt;</button>
        </div>
        <div class="dp-weekdays">
          <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
        </div>
        <div class="dp-days">
      `;
      
      // Empty cells for days before first day of month
      for (let i = 0; i < firstDay; i++) {
        calendarHTML += '<span class="dp-day empty"></span>';
      }
      
      // Days of the month
      for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(year, month, day);
        const isToday = date.getTime() === today.getTime();
        const isSelected = input.value === formatDateValue(date);
        let classes = 'dp-day';
        if (isToday) classes += ' today';
        if (isSelected) classes += ' selected';
        calendarHTML += `<span class="${classes}" data-date="${formatDateValue(date)}">${day}</span>`;
      }
      
      calendarHTML += '</div>';
      dropdown.innerHTML = calendarHTML;
      
      // Event listeners
      dropdown.querySelector('.dp-prev').addEventListener('click', (e) => {
        e.stopPropagation();
        viewDate.setMonth(viewDate.getMonth() - 1);
        renderCalendar();
      });
      
      dropdown.querySelector('.dp-next').addEventListener('click', (e) => {
        e.stopPropagation();
        viewDate.setMonth(viewDate.getMonth() + 1);
        renderCalendar();
      });
      
      dropdown.querySelectorAll('.dp-day:not(.empty)').forEach(dayEl => {
        dayEl.addEventListener('click', (e) => {
          e.stopPropagation();
          const selectedDate = dayEl.dataset.date;
          input.value = selectedDate;
          display.querySelector('.date-display-text').textContent = formatDateDisplay(selectedDate);
          dropdown.classList.remove('open');
          input.dispatchEvent(new Event('change'));
        });
      });
    }
  });
  
  // Close dropdowns when clicking outside
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.date-picker-wrapper') && !e.target.closest('.date-picker-dropdown')) {
      document.querySelectorAll('.date-picker-dropdown.open').forEach(d => {
        d.classList.remove('open');
      });
    }
  });
}

function formatDateValue(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

function formatDateDisplay(dateStr) {
  const date = new Date(dateStr + 'T00:00:00');
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  return `${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
}

// Initialize flatpickr on all date inputs for easy year/month navigation
function initFlatpickrDates() {
  if (typeof flatpickr === 'undefined') return;

  // Determine current theme
  const theme = document.documentElement.getAttribute('data-theme') || 'dark';

  document.querySelectorAll('.dashboard-content input[type="date"]').forEach(input => {
    // Skip if already initialized
    if (input._flatpickr) return;

    flatpickr(input, {
      dateFormat: "Y-m-d",
      allowInput: true,
      altInput: true,
      altFormat: "M j, Y",
      defaultDate: input.value || null,
      // Enable month/year dropdowns for quick navigation
      monthSelectorType: "dropdown",
      animate: true,
      disableMobile: true,
      onChange: function(selectedDates, dateStr) {
        // Trigger change event so forms pick up the value
        input.value = dateStr;
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  });
}
