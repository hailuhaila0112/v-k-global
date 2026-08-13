const ADMIN_API = `${API_BASE_URL}/admin`;

let statusUpdateTarget = null;

function getAdminHeaders() {
  const user = authService.getCurrentUser();
  const headers = { "Content-Type": "application/json" };
  if (user && user.token) {
    headers["Authorization"] = `Bearer ${user.token}`;
  }
  return headers;
}

async function checkAdminAuth() {
  const user = authService.getCurrentUser();
  if (!user || user.role !== 'admin') {
    window.location.href = 'account.html';
    return null;
  }
  return user;
}

async function fetchAdmin(endpoint, options = {}) {
  const timeoutMs = options.timeoutMs || 10000;
  const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
  const timer = controller ? setTimeout(() => controller.abort(), timeoutMs) : null;
  try {
    const res = await fetch(`${ADMIN_API}${endpoint}`, {
      method: options.method || 'GET',
      headers: getAdminHeaders(),
      body: options.body || undefined,
      signal: controller ? controller.signal : undefined
    });
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch (e) {
      return {
        success: false,
        message: `Phản hồi không phải JSON (HTTP ${res.status}): ${text.slice(0, 180)}`
      };
    }
  } catch (e) {
    const msg = e.name === 'AbortError'
      ? `Hết thời gian chờ API (${timeoutMs / 1000}s): ${endpoint}`
      : (e.message || 'Không kết nối được máy chủ');
    return { success: false, message: msg };
  } finally {
    if (timer) clearTimeout(timer);
  }
}

// Sidebar Navigation
function initSidebar() {
  document.querySelectorAll('.nav-item[data-page]').forEach(item => {
    item.addEventListener('click', (e) => {
      e.preventDefault();
      const page = item.dataset.page;
      document.querySelectorAll('.nav-item[data-page]').forEach(n => n.classList.remove('active'));
      item.classList.add('active');
      document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
      document.getElementById(`page-${page}`).classList.add('active');
      // Update topbar title
      const label = item.querySelector('.nav-label')?.textContent || page;
      document.getElementById('pageTitle').textContent = label;
      // Close mobile sidebar
      document.getElementById('adminSidebar').classList.remove('open');
      if (page === 'shipping') loadShippingRates();
    });
  });

  document.getElementById('sidebarToggle').addEventListener('click', () => {
    document.getElementById('adminSidebar').classList.toggle('open');
  });
}

// Logout
document.getElementById('adminLogout').addEventListener('click', (e) => {
  e.preventDefault();
  authService.logout();
  window.location.href = 'account.html';
});

// ===== DASHBOARD =====
async function loadStats() {
  const result = await fetchAdmin('/stats');
  if (!result.success) return;

  const d = result.data;
  document.getElementById('statUsers').textContent = d.totalUsers;
  document.getElementById('statOrders').textContent = d.totalOrders;
  document.getElementById('statRevenue').textContent = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(d.totalRevenue);
  document.getElementById('statProducts').textContent = d.totalProducts;
  document.getElementById('statProjects').textContent = d.totalProjects;
  document.getElementById('statContacts').textContent = d.totalContacts;

  const tbody = document.getElementById('recentOrdersBody');
  if (!d.recentOrders || d.recentOrders.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="table-empty">Chưa có đơn hàng nào.</td></tr>';
    return;
  }

  tbody.innerHTML = d.recentOrders.map(o => `
    <tr>
      <td><strong>${o.order_code}</strong></td>
      <td>${o.user_name}</td>
      <td><strong>${new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(o.total_amount)}</strong></td>
      <td>${o.payment_method}</td>
      <td>${statusBadge(o.status)}</td>
      <td>${formatDate(o.created_at)}</td>
    </tr>
  `).join('');
}

// ===== ORDERS =====
let allOrdersData = [];

async function loadOrders() {
  const result = await fetchAdmin('/orders');
  const grid = document.getElementById('orGrid');
  const data = result.data || [];
  allOrdersData = data;

  // Stats
  document.getElementById('orStatTotal').textContent = data.length;
  document.getElementById('orStatPending').textContent = data.filter(o => o.status === 'Chờ xác nhận').length;
  document.getElementById('orStatProcessing').textContent = data.filter(o => o.status === 'Đang xử lý').length;
  document.getElementById('orStatShipping').textContent = data.filter(o => o.status === 'Đang giao hàng').length;
  document.getElementById('orStatDelivered').textContent = data.filter(o => o.status === 'Đã giao hàng').length;
  document.getElementById('orStatCancelled').textContent = data.filter(o => o.status === 'Đã hủy').length;

  if (!result.success || data.length === 0) {
    grid.innerHTML = `
      <div class="or-empty">
        <span class="or-empty-icon">📦</span>
        <h3>Chưa có đơn hàng nào</h3>
        <p>Đơn hàng từ khách hàng sẽ hiển thị ở đây</p>
      </div>`;
    return;
  }

  grid.innerHTML = data.map(o => {
    const initial = (o.user_name || '?')[0].toUpperCase();
    const items = o.items || [];
    return `
      <div class="or-card">
        <div class="or-card-top">
          <div class="or-card-code">
            <span class="or-card-code-icon">📋</span>
            <div>
              <span class="or-card-code-text">${o.order_code}</span>
              <span class="or-card-code-date">${formatDate(o.created_at)}</span>
            </div>
          </div>
          ${statusBadge(o.status)}
        </div>
        <div class="or-card-body">
          <div class="or-card-customer">
            <span class="or-card-avatar">${initial}</span>
            <div>
              <span class="or-card-name">${o.user_name}</span>
              <span class="or-card-email">${o.user_email}</span>
            </div>
          </div>
          <div class="or-card-items">
            ${items.slice(0, 3).map(item => `
              <div class="or-card-item">
                <img src="${item.product_image || 'https://via.placeholder.com/32'}" alt="" class="or-card-item-img">
                <span class="or-card-item-name">${item.product_name} ×${item.quantity}</span>
              </div>
            `).join('')}
            ${items.length > 3 ? `<span class="or-card-item-more">+${items.length - 3} sản phẩm khác</span>` : ''}
          </div>
          <div class="or-card-meta">
            <span class="or-card-payment">💳 ${o.payment_method}</span>
            <span class="or-card-address">📍 ${o.shipping_address || '—'}</span>
          </div>
        </div>
        <div class="or-card-footer">
          <span class="or-card-total">${formatCurrency(o.total_amount)}</span>
          <button class="or-card-btn" onclick="openStatusModal(${o.id})">
            <span>🔄</span>
            Cập nhật
          </button>
        </div>
      </div>`;
  }).join('');
}

function statusBadge(status) {
  const map = {
    'Chờ xác nhận': 'status-pending',
    'Đang xử lý': 'status-processing',
    'Đang giao hàng': 'status-shipping',
    'Đã giao hàng': 'status-delivered',
    'Đã hủy': 'status-cancelled'
  };
  return `<span class="status-badge ${map[status] || 'status-pending'}">${status}</span>`;
}

function formatDate(dateStr) {
  const d = new Date(dateStr);
  return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// ===== TOAST (standalone for admin page) =====
function showToast(message) {
  let container = document.querySelector('.toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
  }
  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.innerHTML = `<span>${message}</span>`;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.animation = 'slideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1) reverse forwards';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

// ===== ORDER DETAIL MODAL =====

function formatCurrency(val) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
}

function openStatusModal(id) {
  const order = allOrdersData.find(o => o.id == id);
  if (!order) {
    showToast('❌ Không tìm thấy đơn hàng');
    return;
  }

  statusUpdateTarget = id;

  // Header
  document.getElementById('orderDetailCode').textContent = order.order_code;
  document.getElementById('orderDetailDate').textContent = formatDate(order.created_at);
  document.getElementById('orderDetailBadge').innerHTML = statusBadge(order.status);

  // Customer
  const nameInitial = order.user_name?.[0]?.toUpperCase() || '?';
  document.getElementById('orderDetailAvatar').textContent = nameInitial;
  document.getElementById('orderDetailName').textContent = order.user_name;
  document.getElementById('orderDetailEmail').textContent = order.user_email;

  // Payment
  document.getElementById('orderDetailPayment').textContent = order.payment_method;
  document.getElementById('orderDetailAddress').textContent = order.shipping_address || '—';

  // Items
  const items = order.items || [];
  document.getElementById('orderDetailItemCount').textContent = items.length + ' sản phẩm';
  document.getElementById('orderDetailItems').innerHTML = items.map(item => `
    <div class="order-detail-item">
      <img src="${item.product_image || 'https://via.placeholder.com/48'}" alt="" class="order-detail-item-img">
      <div class="order-detail-item-info">
        <div class="order-detail-item-name">${item.product_name}</div>
        <div class="order-detail-item-meta">SL: ${item.quantity} × ${formatCurrency(item.price)}</div>
      </div>
      <div class="order-detail-item-total">${formatCurrency(item.price * item.quantity)}</div>
    </div>
  `).join('');

  // Total
  document.getElementById('orderDetailTotal').textContent = formatCurrency(order.total_amount);

  // Current status label
  document.getElementById('orderDetailCurrentStatus').textContent = 'Hiện tại: ' + order.status;

  // Radio pills — select current
  document.querySelectorAll('.order-status-option').forEach(opt => {
    const radio = opt.querySelector('input');
    radio.checked = radio.value === order.status;
    opt.classList.toggle('active', radio.checked);
  });

  document.getElementById('statusModal').classList.add('active');
}

function closeStatusModal() {
  document.getElementById('statusModal').classList.remove('active');
  statusUpdateTarget = null;
}

// Radio pill click handler
document.addEventListener('click', function (e) {
  const option = e.target.closest('.order-status-option');
  if (option) {
    option.querySelector('input').checked = true;
    document.querySelectorAll('.order-status-option').forEach(o => o.classList.remove('active'));
    option.classList.add('active');
  }
});

async function confirmStatusUpdate() {
  if (!statusUpdateTarget) return;
  const selected = document.querySelector('input[name="orderStatus"]:checked');
  if (!selected) {
    showToast('⚠️ Vui lòng chọn trạng thái');
    return;
  }
  const status = selected.value;

  const res = await fetch(`${ADMIN_API}/orders/status`, {
    method: 'PUT',
    headers: getAdminHeaders(),
    body: JSON.stringify({ order_id: statusUpdateTarget, status })
  });
  const data = await res.json();
  if (data.success) {
    closeStatusModal();
    loadOrders();
    showSuccessNotification(
      'Cập nhật trạng thái!',
      `Đơn hàng đã chuyển sang "<strong>${status}</strong>"`,
      ''
    );
  } else {
    showToast('❌ ' + data.message);
  }
}

// ===== PROJECTS CRUD =====
let projectTechData = [];

async function loadProjects() {
  const result = await fetchAdmin('/projects');
  const grid = document.getElementById('pjGrid');
  const data = result.data || [];

  // Stats
  document.getElementById('pjStatTotal').textContent = data.length;
  document.getElementById('pjStatDev').textContent = data.filter(p => p.status === 'Đang phát triển').length;
  document.getElementById('pjStatTest').textContent = data.filter(p => p.status === 'Đang thử nghiệm').length;
  document.getElementById('pjStatDone').textContent = data.filter(p => p.status === 'Hoàn thành').length;

  if (!result.success || data.length === 0) {
    grid.innerHTML = `
      <div class="pj-empty">
        <span class="pj-empty-icon">🚀</span>
        <h3>Chưa có dự án nào</h3>
        <p>Nhấn "Thêm dự án" để bắt đầu</p>
      </div>`;
    return;
  }

  grid.innerHTML = data.map((p, i) => `
    <div class="pj-card" onclick="editProject(${p.id})">
      <div class="pj-card-img-wrap">
        <img src="${p.image || 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=600&q=80'}" alt="" class="pj-card-img">
        <div class="pj-card-img-overlay"></div>
        <span class="pj-card-badge">${p.category || 'Công nghệ'}</span>
        <span class="pj-card-progress-badge">${p.progress}</span>
      </div>
      <div class="pj-card-body">
        <h3 class="pj-card-title">${p.name}</h3>
        <p class="pj-card-desc">${p.description || ''}</p>
        <div class="pj-card-progress">
          <div class="pj-card-progress-track">
            <div class="pj-card-progress-bar" style="width:${p.progress};"></div>
          </div>
          <span class="pj-card-progress-label">${p.progress}</span>
        </div>
        <div class="pj-card-techs">
          ${(p.technologies || []).slice(0, 4).map(t =>
            `<span class="pj-card-tech">${t}</span>`
          ).join('')}
          ${(p.technologies || []).length > 4 ? `<span class="pj-card-tech-more">+${(p.technologies || []).length - 4}</span>` : ''}
        </div>
        <div class="pj-card-footer">
          <span class="pj-card-status ${p.status === 'Đang phát triển' ? 'pj-status-dev' : p.status === 'Đang thử nghiệm' ? 'pj-status-test' : 'pj-status-done'}">
            <span class="pj-card-status-dot"></span>
            ${p.status}
          </span>
          <div class="pj-card-actions" onclick="event.stopPropagation()">
            <button class="pj-card-action edit" onclick="editProject(${p.id})" title="Sửa">✏️</button>
            <button class="pj-card-action delete" onclick="openDeleteModal('project', ${p.id}, '${p.name.replace(/'/g, "\\'")}')" title="Xóa">🗑️</button>
          </div>
        </div>
      </div>
    </div>
  `).join('');
}

document.getElementById('syncProjectsBtn')?.addEventListener('click', loadProjects);

// Tech tags
function renderProjectTech() {
  const list = document.getElementById('pj-tech-list');
  if (!list) return;
  list.innerHTML = projectTechData.map((t, i) => `
    <span class="pj-tech-chip">
      <span>${t}</span>
      <button type="button" class="pj-tech-remove" onclick="removeProjectTech(${i})">✕</button>
    </span>
  `).join('');
}

function addProjectTech() {
  const input = document.getElementById('pj-tech-input');
  const val = input.value.trim();
  if (val && !projectTechData.includes(val)) {
    projectTechData.push(val);
    renderProjectTech();
    input.value = '';
  }
  input.focus();
}

function removeProjectTech(idx) {
  projectTechData.splice(idx, 1);
  renderProjectTech();
}

document.getElementById('pj-tech-add')?.addEventListener('click', addProjectTech);
document.getElementById('pj-tech-input')?.addEventListener('keydown', function (e) {
  if (e.key === 'Enter') { e.preventDefault(); addProjectTech(); }
});

// Status pill click
document.querySelectorAll('.pj-status-pill').forEach(pill => {
  pill.addEventListener('click', function () {
    this.querySelector('input').checked = true;
    document.querySelectorAll('.pj-status-pill').forEach(p => p.classList.remove('active'));
    this.classList.add('active');
  });
});

// Image preview
document.getElementById('pj-image')?.addEventListener('input', function () {
  const url = this.value.trim();
  const img = document.getElementById('pjPreview');
  const placeholder = document.getElementById('pjDzPlaceholder');
  if (url) {
    img.src = url;
    img.classList.remove('hidden');
    placeholder.classList.add('hidden');
  } else {
    img.classList.add('hidden');
    placeholder.classList.remove('hidden');
  }
});

document.getElementById('pjDropzone')?.addEventListener('click', function () {
  document.getElementById('pj-image').focus();
});

function openProjectModal(project) {
  projectTechData = [];
  renderProjectTech();

  // Reset status pills
  document.querySelectorAll('.pj-status-pill').forEach(p => p.classList.remove('active'));

  document.getElementById('pj-id').value = project?.id || '';
  document.getElementById('pj-name').value = project?.name || '';
  document.getElementById('pj-category').value = project?.category || '';
  document.getElementById('pj-image').value = project?.image || '';
  document.getElementById('pj-image').dispatchEvent(new Event('input'));
  document.getElementById('pj-description').value = project?.description || '';
  document.getElementById('pj-progress').value = project?.progress ? parseInt(project.progress) : 0;

  // Status pill
  const status = project?.status || 'Đang phát triển';
  document.querySelectorAll('.pj-status-pill').forEach(pill => {
    const radio = pill.querySelector('input');
    radio.checked = radio.value === status;
    pill.classList.toggle('active', radio.checked);
  });

  if (project?.technologies) {
    const arr = typeof project.technologies === 'string' ? JSON.parse(project.technologies) : project.technologies;
    projectTechData = arr || [];
    renderProjectTech();
  }

  const isEdit = !!project;
  document.getElementById('pjModalTitle').textContent = isEdit ? 'Sửa dự án' : 'Thêm dự án';
  document.getElementById('pjModalSub').textContent = isEdit ? 'Chỉnh sửa thông tin dự án' : 'Nhập thông tin dự án mới';
  document.getElementById('pjModalBadge').textContent = '🚀';
  document.getElementById('pj-submit-text').textContent = isEdit ? 'Cập nhật dự án' : 'Thêm dự án';

  document.getElementById('projectModal').classList.add('active');
}

function closeProjectModal() {
  document.getElementById('projectModal').classList.remove('active');
}

async function saveProject(e) {
  e.preventDefault();
  const id = document.getElementById('pj-id').value;

  const selectedStatus = document.querySelector('input[name="pjStatus"]:checked');
  const status = selectedStatus ? selectedStatus.value : 'Đang phát triển';

  const body = {
    name: document.getElementById('pj-name').value.trim(),
    category: document.getElementById('pj-category').value.trim(),
    image: document.getElementById('pj-image').value.trim(),
    description: document.getElementById('pj-description').value.trim(),
    technologies: projectTechData,
    progress: (parseInt(document.getElementById('pj-progress').value) || 0) + '%',
    status: status
  };

  if (!body.name) {
    showToast('⚠️ Vui lòng nhập tên dự án');
    return;
  }

  if (id) body.id = parseInt(id);
  const method = id ? 'PUT' : 'POST';

  const res = await fetch(`${ADMIN_API}/projects`, {
    method,
    headers: getAdminHeaders(),
    body: JSON.stringify(body)
  });
  const data = await res.json();

  if (data.success) {
    closeProjectModal();
    loadProjects();
    showSuccessNotification(
      id ? 'Cập nhật thành công!' : 'Thêm thành công!',
      `Dự án "<strong>${body.name}</strong>" đã được ${id ? 'cập nhật' : 'thêm vào'}`,
      body.name
    );
  } else {
    showToast('❌ ' + data.message);
  }
}

async function editProject(id) {
  const result = await fetchAdmin('/projects');
  if (!result.success || !result.data) return;
  const project = result.data.find(p => p.id == id);
  if (project) openProjectModal(project);
}

// ===== SLIDER CRUD =====
async function loadSliders() {
  const result = await fetchAdmin('/sliders');
  const grid = document.getElementById('sliderGrid');
  const data = result.data || [];

  // Stats
  document.getElementById('slStatTotal').textContent = data.length;
  document.getElementById('slStatActive').textContent = data.filter(s => s.status == 1).length;
  document.getElementById('slStatHidden').textContent = data.filter(s => s.status == 0).length;

  if (!result.success || data.length === 0) {
    grid.innerHTML = `
      <div class="slider-designer-empty">
        <span class="slider-designer-empty-icon">🎠</span>
        <h3>Chưa có slide nào</h3>
        <p>Nhấn "Thêm slide" để bắt đầu tạo slider</p>
      </div>`;
    return;
  }

  grid.innerHTML = data.map((s, i) => `
    <div class="slide-card ${s.status ? '' : 'muted'}" data-id="${s.id}" onclick="editSlider(${s.id})">
      <div class="slide-card-drag">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="5" r="1.5"/><circle cx="15" cy="5" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="19" r="1.5"/></svg>
      </div>
      <img src="${s.image || 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=600&q=80'}" alt="" class="slide-card-img" draggable="false">
      <div class="slide-card-overlay"></div>
      <span class="slide-card-order">${i + 1}</span>
      <span class="slide-card-badge">${s.status ? 'Hiển thị' : 'Ẩn'}</span>
      <div class="slide-card-content">
        <span class="slide-card-icon">${s.icon || '🎠'}</span>
        <h3 class="slide-card-title">${s.title}</h3>
        <p class="slide-card-desc">${s.description || ''}</p>
      </div>
      <div class="slide-card-actions" onclick="event.stopPropagation()">
        <button class="slide-action-btn edit" onclick="editSlider(${s.id})" title="Sửa">✏️</button>
        <button class="slide-action-btn delete" onclick="openDeleteModal('slider', ${s.id}, '${s.title.replace(/'/g, "\\'")}')" title="Xóa">🗑️</button>
      </div>
    </div>
  `).join('');

  // Init SortableJS for drag-and-drop reorder
  if (window.Sortable && grid._sortable) {
    grid._sortable.destroy();
  }
  if (window.Sortable) {
    grid._sortable = new Sortable(grid, {
      animation: 300,
      easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
      ghostClass: 'slide-card-ghost',
      dragClass: 'slide-card-dragging',
      onEnd: async function (evt) {
        const cards = grid.querySelectorAll('.slide-card');
        const orderEl = document.getElementById('sliderOrder');
        cards.forEach((card, idx) => {
          const newOrder = idx + 1;
          card.querySelector('.slide-card-order').textContent = newOrder;
        });
        // Send all updates in a single batch
        const batch = Array.from(cards).map((card, idx) => ({
          id: parseInt(card.dataset.id),
          sort_order: idx + 1
        }));
        try {
          const res = await fetch(`${ADMIN_API}/sliders/reorder`, {
            method: 'PUT',
            headers: getAdminHeaders(),
            body: JSON.stringify({ items: batch })
          });
          const data = await res.json();
          if (data.success) {
            showToast('✅ Đã sắp xếp lại thứ tự slider');
          } else {
            showToast('❌ ' + (data.message || 'Lỗi sắp xếp'));
          }
        } catch (e) {
          showToast('❌ Lỗi kết nối');
        }
      }
    });
  }
}

document.getElementById('syncSlidersBtn')?.addEventListener('click', loadSliders);

// Emoji picker
document.querySelectorAll('.slider-emoji-opt').forEach(btn => {
  btn.addEventListener('click', function () {
    document.querySelectorAll('.slider-emoji-opt').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    document.getElementById('sl-icon').value = this.dataset.emoji;
  });
});

function openSliderModal(slider) {
  // Reset emoji
  document.querySelectorAll('.slider-emoji-opt').forEach(b => b.classList.remove('active'));

  document.getElementById('sl-id').value = slider?.id || '';
  document.getElementById('sl-image').value = slider?.image || '';
  document.getElementById('sl-image').dispatchEvent(new Event('input'));
  document.getElementById('sl-icon').value = slider?.icon || '';
  document.getElementById('sl-title').value = slider?.title || '';
  document.getElementById('sl-desc').value = slider?.description || '';
  document.getElementById('sl-sort').value = slider?.sort_order ?? 0;
  document.getElementById('sl-status').checked = slider ? slider.status == 1 : true;
  document.getElementById('sl-status').dispatchEvent(new Event('change'));

  // Select emoji
  const icon = slider?.icon || '';
  document.querySelectorAll('.slider-emoji-opt').forEach(b => {
    if (b.dataset.emoji === icon) b.classList.add('active');
  });

  const isEdit = !!slider;
  document.getElementById('sliderModalTitle').textContent = isEdit ? 'Sửa slide' : 'Thêm slide';
  document.getElementById('sliderModalSub').textContent = isEdit ? 'Chỉnh sửa thông tin slide' : 'Nhập thông tin slide mới';
  document.getElementById('sliderModalBadge').textContent = icon || '🎠';
  document.getElementById('sl-submit-text').textContent = isEdit ? 'Cập nhật slide' : 'Thêm slide';

  document.getElementById('sliderModal').classList.add('active');
}

function closeSliderModal() {
  document.getElementById('sliderModal').classList.remove('active');
}

// Image preview via input
document.getElementById('sl-image')?.addEventListener('input', function () {
  const url = this.value.trim();
  const img = document.getElementById('sliderPreview');
  const placeholder = document.getElementById('sliderDzPlaceholder');
  if (url) {
    img.src = url;
    img.classList.remove('hidden');
    placeholder.classList.add('hidden');
  } else {
    img.classList.add('hidden');
    placeholder.classList.remove('hidden');
  }
});

// Dropzone click = focus image input
document.getElementById('sliderDropzone')?.addEventListener('click', function () {
  document.getElementById('sl-image').focus();
});

// Toggle status
document.getElementById('sl-status')?.addEventListener('change', function () {
  const label = document.getElementById('sl-status-label');
  if (this.checked) {
    label.textContent = 'Hiện';
    label.className = 'status-indicator status-active';
  } else {
    label.textContent = 'Ẩn';
    label.className = 'status-indicator status-inactive';
  }
});

async function saveSlider(e) {
  e.preventDefault();
  const id = document.getElementById('sl-id').value;

  const body = {
    image: document.getElementById('sl-image').value.trim(),
    icon: document.getElementById('sl-icon').value || '',
    title: document.getElementById('sl-title').value.trim(),
    description: document.getElementById('sl-desc').value.trim(),
    sort_order: parseInt(document.getElementById('sl-sort').value) || 0,
    status: document.getElementById('sl-status').checked ? 1 : 0
  };

  if (!body.title) {
    showToast('⚠️ Vui lòng nhập tiêu đề');
    return;
  }

  if (id) body.id = parseInt(id);
  const method = id ? 'PUT' : 'POST';

  const res = await fetch(`${ADMIN_API}/sliders`, {
    method,
    headers: getAdminHeaders(),
    body: JSON.stringify(body)
  });
  const data = await res.json();
  if (data.success) {
    closeSliderModal();
    loadSliders();
    showSuccessNotification(
      id ? 'Cập nhật thành công!' : 'Thêm thành công!',
      `Slide "<strong>${body.title}</strong>" đã được ${id ? 'cập nhật' : 'thêm vào'}`,
      ''
    );
  } else {
    showToast('❌ ' + data.message);
  }
}

async function editSlider(id) {
  const result = await fetchAdmin('/sliders');
  if (!result.success || !result.data) return;
  const slider = result.data.find(s => s.id == id);
  if (slider) openSliderModal(slider);
}

// ===== GENERIC DELETE =====
let deleteTargetId = null;
let deleteTargetType = null;
let deleteTargetCallback = null;

function openDeleteModal(type, id, name) {
  deleteTargetType = type;
  deleteTargetId = id;
  document.getElementById('deleteModalInfo').textContent = `Xóa "${name}"?`;
  const msgs = { product: 'Sản phẩm', project: 'Dự án', slider: 'Slider', user: 'Người dùng', message: 'Tin nhắn', shipping: 'Phí vận chuyển' };
  document.getElementById('deleteModalSub').textContent = (msgs[type] || 'Mục') + ' sẽ bị xóa vĩnh viễn.';
  document.getElementById('deleteModal').classList.add('active');
}

function closeDeleteModal() {
  document.getElementById('deleteModal').classList.remove('active');
  deleteTargetId = null;
  deleteTargetType = null;
}

async function confirmDeleteAction() {
  if (!deleteTargetId || !deleteTargetType) return;

  let endpoint, successMsg, callback;
  if (deleteTargetType === 'product') {
    endpoint = `${ADMIN_API}/products`;
    successMsg = 'Sản phẩm đã được xóa';
    callback = loadProducts;
  } else if (deleteTargetType === 'project') {
    endpoint = `${ADMIN_API}/projects`;
    successMsg = 'Dự án đã được xóa';
    callback = loadProjects;
  } else if (deleteTargetType === 'slider') {
    endpoint = `${ADMIN_API}/sliders`;
    successMsg = 'Slider đã được xóa';
    callback = loadSliders;
  } else if (deleteTargetType === 'user') {
    endpoint = `${ADMIN_API}/users/delete`;
    successMsg = 'Người dùng đã được xóa';
    callback = loadUsers;
  } else if (deleteTargetType === 'message') {
    endpoint = `${ADMIN_API}/messages/delete`;
    successMsg = 'Tin nhắn đã được xóa';
    callback = loadMessages;
  } else if (deleteTargetType === 'shipping') {
    endpoint = `${ADMIN_API}/shipping-rates`;
    successMsg = 'Phí vận chuyển đã được xóa';
    callback = loadShippingRates;
  } else {
    return;
  }

  const res = await fetch(endpoint, {
    method: 'DELETE',
    headers: getAdminHeaders(),
    body: JSON.stringify({ id: deleteTargetId })
  });
  const data = await res.json();
  if (data.success) {
    closeDeleteModal();
    callback();
    showSuccessNotification('Đã xóa!', successMsg, '');
  } else {
    showToast('❌ ' + data.message);
  }
}

// ===== PRODUCTS CRUD =====

let featuresData = [];
let specsData = [];

async function loadProducts() {
  const result = await fetchAdmin('/products');
  const grid = document.getElementById('prGrid');
  const data = result.data || [];

  // Stats
  document.getElementById('prStatTotal').textContent = data.length;
  document.getElementById('prStatActive').textContent = data.filter(p => p.status == 1).length;
  document.getElementById('prStatHidden').textContent = data.filter(p => p.status == 0).length;
  document.getElementById('prStatStock').textContent = data.filter(p => p.stock == 0 || p.stock === null).length;

  if (!result.success || data.length === 0) {
    grid.innerHTML = `
      <div class="pr-empty">
        <span class="pr-empty-icon">🛒</span>
        <h3>Chưa có sản phẩm nào</h3>
        <p>Nhấn "Thêm sản phẩm" để bắt đầu</p>
      </div>`;
    return;
  }

  grid.innerHTML = data.map(p => `
    <div class="pr-card">
      <div class="pr-card-img-wrap">
        <img src="${p.image || 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=600&q=80'}" alt="" class="pr-card-img">
        <span class="pr-card-badge ${p.badge ? 'pr-badge-' + p.badge.toLowerCase().replace(/\s+/g, '-') : 'pr-badge-none'}">${p.badge || ''}</span>
        <span class="pr-card-status ${p.status == 1 ? 'pr-status-on' : 'pr-status-off'}">${p.status == 1 ? 'Đang bán' : 'Ngừng bán'}</span>
      </div>
      <div class="pr-card-body">
        <h3 class="pr-card-title">${p.name}</h3>
        <div class="pr-card-meta">
          <span class="pr-card-brand">${p.brand_name || ''}</span>
          <span class="pr-card-category">${p.category_name || ''}</span>
        </div>
        <div class="pr-card-pricing">
          <span class="pr-card-price">${formatCurrency(p.price)}</span>
          ${p.original_price ? `<span class="pr-card-original">${formatCurrency(p.original_price)}</span>` : ''}
        </div>
        <div class="pr-card-stock">
          <span class="pr-card-stock-dot ${p.stock > 0 ? 'stock-ok' : 'stock-low'}"></span>
          <span>${p.stock > 0 ? `Còn ${p.stock} sản phẩm` : 'Hết hàng'}</span>
        </div>
      </div>
      <div class="pr-card-footer">
        <button class="pr-card-action edit" onclick="editProduct(${p.id})" title="Sửa">✏️</button>
        <button class="pr-card-action delete" onclick="openDeleteProductModal(${p.id}, '${p.name.replace(/'/g, "\\'")}')" title="Xóa">🗑️</button>
      </div>
    </div>
  `).join('');
}

document.getElementById('syncProductsBtn')?.addEventListener('click', loadProducts);

// ===== AUTO SLUG =====
function autoSlug(name) {
  const slug = document.getElementById('pf-slug');
  if (!slug.dataset.manual) {
    slug.value = name.toLowerCase()
      .replace(/à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ/g, 'a')
      .replace(/è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ/g, 'e')
      .replace(/ì|í|ị|ỉ|ĩ/g, 'i')
      .replace(/ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ/g, 'o')
      .replace(/ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ/g, 'u')
      .replace(/ỳ|ý|ỵ|ỷ|ỹ/g, 'y')
      .replace(/đ/g, 'd')
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-|-$/g, '');
  }
}

document.getElementById('pf-slug')?.addEventListener('input', function () {
  this.dataset.manual = 'true';
});
document.getElementById('pf-name')?.addEventListener('input', function () {
  autoSlug(this.value);
});

// ===== IMAGE PREVIEW =====
document.getElementById('pf-image')?.addEventListener('input', function () {
  const url = this.value.trim();
  const preview = document.getElementById('imagePreview');
  const placeholder = document.getElementById('imagePlaceholder');
  const removeBtn = document.getElementById('imageRemoveBtn');
  if (url) {
    preview.src = url;
    preview.classList.remove('hidden');
    placeholder.classList.add('hidden');
    removeBtn.classList.remove('hidden');
  } else {
    preview.classList.add('hidden');
    placeholder.classList.remove('hidden');
    removeBtn.classList.add('hidden');
  }
});

document.getElementById('imageRemoveBtn')?.addEventListener('click', function () {
  document.getElementById('pf-image').value = '';
  document.getElementById('pf-image').dispatchEvent(new Event('input'));
});

// ===== TOGGLE STATUS =====
document.getElementById('pf-status')?.addEventListener('change', function () {
  const label = document.getElementById('pf-status-label');
  if (this.checked) {
    label.textContent = 'Đang bán';
    label.className = 'status-indicator status-active';
  } else {
    label.textContent = 'Ngừng bán';
    label.className = 'status-indicator status-inactive';
  }
});

// ===== FEATURES (Tag input) =====
function renderFeatures() {
  const list = document.getElementById('featuresList');
  list.innerHTML = featuresData.map((f, i) => `
    <span class="tag-chip">
      ${f}
      <button type="button" class="tag-remove" onclick="removeFeature(${i})">✕</button>
    </span>
  `).join('');
  document.getElementById('pf-features').value = featuresData.join('\n');
}

function addFeature() {
  const input = document.getElementById('pf-features-input');
  const val = input.value.trim();
  if (val && !featuresData.includes(val)) {
    featuresData.push(val);
    renderFeatures();
    input.value = '';
  }
  input.focus();
}

function removeFeature(idx) {
  featuresData.splice(idx, 1);
  renderFeatures();
}

document.getElementById('featuresAddBtn')?.addEventListener('click', addFeature);
document.getElementById('pf-features-input')?.addEventListener('keydown', function (e) {
  if (e.key === 'Enter') { e.preventDefault(); addFeature(); }
});

// ===== SPECS (Key-Value) =====
function renderSpecs() {
  const list = document.getElementById('specsList');
  list.innerHTML = specsData.map((s, i) => `
    <div class="spec-row">
      <span class="spec-row-key">${s.key}</span>
      <span class="spec-row-value">${s.value}</span>
      <button type="button" class="spec-row-remove" onclick="removeSpec(${i})">✕</button>
    </div>
  `).join('');
  document.getElementById('pf-specs').value = specsData.map(s => `${s.key}: ${s.value}`).join('\n');
}

function addSpec() {
  const key = document.getElementById('pf-specs-key').value.trim();
  const val = document.getElementById('pf-specs-value').value.trim();
  if (key && val) {
    specsData.push({ key, value: val });
    renderSpecs();
    document.getElementById('pf-specs-key').value = '';
    document.getElementById('pf-specs-value').value = '';
  }
  document.getElementById('pf-specs-key').focus();
}

function removeSpec(idx) {
  specsData.splice(idx, 1);
  renderSpecs();
}

document.getElementById('specsAddBtn')?.addEventListener('click', addSpec);
document.getElementById('pf-specs-value')?.addEventListener('keydown', function (e) {
  if (e.key === 'Enter') { e.preventDefault(); addSpec(); }
});

// ===== CATEGORY & BRAND =====
async function loadCategoryBrandOptions() {
  const [cats, brands] = await Promise.all([
    fetchAdmin('/categories'),
    fetchAdmin('/brands')
  ]);
  const catSelect = document.getElementById('pf-category');
  catSelect.innerHTML = '<option value="">Chọn danh mục</option>' +
    (cats.data || []).map(c => `<option value="${c.id}">${c.name}</option>`).join('');

  const brandSelect = document.getElementById('pf-brand');
  brandSelect.innerHTML = '<option value="">Chọn thương hiệu</option>' +
    (brands.data || []).map(b => `<option value="${b.id}">${b.name}</option>`).join('');
}

// ===== OPEN / CLOSE MODAL =====
function openProductModal(product) {
  loadCategoryBrandOptions();

  // Reset data
  featuresData = [];
  specsData = [];
  renderFeatures();
  renderSpecs();

  document.getElementById('pf-id').value = product?.id || '';
  document.getElementById('pf-name').value = product?.name || '';
  document.getElementById('pf-slug').value = product?.slug || '';
  document.getElementById('pf-slug').dataset.manual = product?.slug ? 'true' : '';
  document.getElementById('pf-price').value = product?.price || '';
  document.getElementById('pf-original-price').value = product?.original_price || '';
  document.getElementById('pf-stock').value = product?.stock ?? 0;

  // Image
  document.getElementById('pf-image').value = product?.image || '';
  document.getElementById('pf-image').dispatchEvent(new Event('input'));

  document.getElementById('pf-short-desc').value = product?.short_description || '';
  document.getElementById('pf-desc').value = product?.description || '';

  // Features
  if (product?.features) {
    const arr = typeof product.features === 'string' ? JSON.parse(product.features) : product.features;
    featuresData = arr || [];
    renderFeatures();
  }

  // Specs
  if (product?.specs) {
    const obj = typeof product.specs === 'string' ? JSON.parse(product.specs) : product.specs;
    specsData = obj ? Object.entries(obj).map(([k, v]) => ({ key: k, value: v })) : [];
    renderSpecs();
  }

  // Badge radio
  const badgeVal = product?.badge || '';
  document.querySelectorAll('input[name="pf-badge"]').forEach(r => {
    r.checked = r.value === badgeVal;
  });

  // Status toggle
  const statusOn = product ? product.status == 1 : true;
  document.getElementById('pf-status').checked = statusOn;
  document.getElementById('pf-status').dispatchEvent(new Event('change'));

  // Title / button
  const isEdit = !!product;
  document.getElementById('productModalTitle').textContent = isEdit ? 'Sửa sản phẩm' : 'Thêm sản phẩm';
  document.getElementById('productModalSub').textContent = isEdit ? 'Chỉnh sửa thông tin sản phẩm' : 'Nhập thông tin sản phẩm mới';
  document.getElementById('productModalBadge').textContent = isEdit ? '✏️' : '📦';
  document.getElementById('pf-submit-text').textContent = isEdit ? 'Cập nhật sản phẩm' : 'Thêm sản phẩm';

  // Select category/brand after options loaded
  if (product) {
    setTimeout(() => {
      if (document.getElementById('pf-category').querySelector(`option[value="${product.category_id}"]`)) {
        document.getElementById('pf-category').value = product.category_id;
      }
      if (document.getElementById('pf-brand').querySelector(`option[value="${product.brand_id}"]`)) {
        document.getElementById('pf-brand').value = product.brand_id;
      }
    }, 150);
  }

  document.getElementById('productModal').classList.add('active');
}

function closeProductModal() {
  document.getElementById('productModal').classList.remove('active');
}

// ===== SAVE =====
async function saveProduct(e) {
  e.preventDefault();
  const id = document.getElementById('pf-id').value;

  const body = {
    name: document.getElementById('pf-name').value.trim(),
    slug: document.getElementById('pf-slug').value.trim(),
    category_id: parseInt(document.getElementById('pf-category').value),
    brand_id: parseInt(document.getElementById('pf-brand').value),
    price: parseFloat(document.getElementById('pf-price').value),
    original_price: parseFloat(document.getElementById('pf-original-price').value) || null,
    stock: parseInt(document.getElementById('pf-stock').value) || 0,
    badge: document.querySelector('input[name="pf-badge"]:checked')?.value || '',
    image: document.getElementById('pf-image').value.trim(),
    short_description: document.getElementById('pf-short-desc').value.trim(),
    description: document.getElementById('pf-desc').value.trim(),
    features: featuresData,
    specs: specsData.reduce((acc, s) => { acc[s.key] = s.value; return acc; }, {}),
    status: document.getElementById('pf-status').checked ? 1 : 0
  };

  if (id) body.id = parseInt(id);

  const method = id ? 'PUT' : 'POST';

  const res = await fetch(`${ADMIN_API}/products`, {
    method,
    headers: getAdminHeaders(),
    body: JSON.stringify(body)
  });
  const data = await res.json();

  if (data.success) {
    closeProductModal();
    loadProducts();
    const isEdit = !!id;
    const productName = document.getElementById('pf-name').value.trim() || 'Sản phẩm';
    showSuccessNotification(
      isEdit ? 'Cập nhật thành công!' : 'Thêm thành công!',
      isEdit
        ? `Sản phẩm "<strong>${productName}</strong>" đã được cập nhật`
        : `Sản phẩm "<strong>${productName}</strong>" đã được thêm vào danh sách`,
      productName
    );
  } else {
    showToast('❌ ' + data.message);
  }
}

// ===== EDIT =====
async function editProduct(id) {
  const result = await fetchAdmin('/products');
  if (!result.success || !result.data) return;
  const product = result.data.find(p => p.id == id);
  if (product) openProductModal(product);
}

// ===== DELETE (products use generic) =====
function openDeleteProductModal(id, name) {
  openDeleteModal('product', id, name);
}

// ===== SUCCESS NOTIFICATION =====
function showSuccessNotification(title, subtitle, detail) {
  const overlay = document.getElementById('successOverlay');
  if (!overlay) return;

  document.getElementById('successTitle').textContent = title;
  document.getElementById('successSub').innerHTML = subtitle;

  const detailEl = document.getElementById('successDetail');
  if (detail) {
    detailEl.textContent = detail;
    detailEl.style.display = 'inline-block';
  } else {
    detailEl.style.display = 'none';
  }

  // Reset progress bar by re-creating pseudo-element
  overlay.classList.add('active');

  // Auto dismiss after 3s
  setTimeout(() => {
    overlay.classList.remove('active');
  }, 3000);
}

// Close on click anywhere
document.getElementById('successOverlay')?.addEventListener('click', function () {
  this.classList.remove('active');
});

// ===== USERS =====
let currentUserId = null;

async function loadUsers() {
  const result = await fetchAdmin('/users');
  const grid = document.getElementById('usGrid');
  const data = result.data || [];

  // Stats
  const now = new Date();
  const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
  document.getElementById('usStatTotal').textContent = data.length;
  document.getElementById('usStatAdmin').textContent = data.filter(u => u.role_name === 'admin').length;
  document.getElementById('usStatCustomer').textContent = data.filter(u => u.role_name !== 'admin').length;
  document.getElementById('usStatNew').textContent = data.filter(u => new Date(u.created_at) > weekAgo).length;

  if (!result.success || data.length === 0) {
    grid.innerHTML = `
      <div class="us-empty">
        <span class="us-empty-icon">👥</span>
        <h3>Chưa có người dùng nào</h3>
        <p>Nhấn "Thêm người dùng" để tạo tài khoản</p>
      </div>`;
    return;
  }

  grid.innerHTML = data.map(u => {
    const isAdmin = u.role_name === 'admin';
    const initial = (u.name || '?')[0].toUpperCase();
    return `
      <div class="us-card">
        <div class="us-card-body">
          <div class="us-card-avatar">${initial}</div>
          <div class="us-card-name">${u.name}</div>
          <div class="us-card-email">${u.email}</div>
          <div class="us-card-phone">${u.phone || '—'}</div>
          <span class="us-card-role ${isAdmin ? 'admin' : 'customer'}">
            ${isAdmin ? '👑' : '😊'} ${isAdmin ? 'Admin' : 'Khách hàng'}
          </span>
          <div style="font-size:11px;color:#94a3b8;margin-top:8px;">Tham gia ${formatDate(u.created_at)}</div>
        </div>
        <div class="us-card-footer">
          <button class="us-card-action" onclick="editUser(${u.id})" title="Sửa">✏️</button>
          <button class="us-card-action delete" onclick="openDeleteModal('user',${u.id},'${u.name.replace(/'/g, "\\'")}')" title="Xóa">🗑️</button>
        </div>
      </div>`;
  }).join('');
}

// Role pill click
document.querySelectorAll('.us-role-pill').forEach(pill => {
  pill.addEventListener('click', function () {
    this.querySelector('input').checked = true;
    document.querySelectorAll('.us-role-pill').forEach(p => p.classList.remove('active'));
    this.classList.add('active');
    // Update avatar emoji
    const isAdmin = this.querySelector('input').value === '1';
    document.getElementById('usAvatarEmoji').textContent = isAdmin ? '👑' : '👤';
  });
});

function openUserModal(user) {
  currentUserId = user?.id || null;

  document.getElementById('us-id').value = currentUserId || '';
  document.getElementById('us-name').value = user?.name || '';
  document.getElementById('us-email').value = user?.email || '';
  document.getElementById('us-phone').value = user?.phone || '';
  document.getElementById('us-password').value = '';

  // Reset role pills
  document.querySelectorAll('.us-role-pill').forEach(p => p.classList.remove('active'));
  const roleVal = user?.role_id ? String(user.role_id) : '2';
  document.querySelectorAll('.us-role-pill').forEach(pill => {
    const radio = pill.querySelector('input');
    radio.checked = radio.value === roleVal;
    pill.classList.toggle('active', radio.checked);
  });

  // Update avatar
  document.getElementById('usAvatarEmoji').textContent = roleVal === '1' ? '👑' : '👤';

  // Show/hide password note
  const isEdit = !!user;
  document.getElementById('usPassReq').style.display = isEdit ? 'none' : 'inline';
  document.getElementById('usPassNote').style.display = isEdit ? 'block' : 'none';
  document.getElementById('us-password').required = !isEdit;

  document.getElementById('usModalTitle').textContent = isEdit ? 'Sửa người dùng' : 'Thêm người dùng';
  document.getElementById('usModalSub').textContent = isEdit ? 'Chỉnh sửa thông tin tài khoản' : 'Nhập thông tin tài khoản mới';
  document.getElementById('us-submit-text').textContent = isEdit ? 'Cập nhật' : 'Thêm người dùng';

  document.getElementById('userModal').classList.add('active');
}

function closeUserModal() {
  document.getElementById('userModal').classList.remove('active');
  currentUserId = null;
}

async function saveUser(e) {
  e.preventDefault();
  const id = document.getElementById('us-id').value;

  const selected = document.querySelector('input[name="usRole"]:checked');
  const role_id = parseInt(selected ? selected.value : '2');

  const body = {
    name: document.getElementById('us-name').value.trim(),
    email: document.getElementById('us-email').value.trim(),
    phone: document.getElementById('us-phone').value.trim(),
    role_id: role_id,
    password: document.getElementById('us-password').value
  };

  if (!body.name || !body.email) {
    showToast('⚠️ Vui lòng nhập tên và email');
    return;
  }

  const isEdit = !!id;
  let url, method;

  if (isEdit) {
    body.id = parseInt(id);
    url = `${ADMIN_API}/users/update`;
    method = 'PUT';
  } else {
    if (!body.password) {
      showToast('⚠️ Vui lòng nhập mật khẩu');
      return;
    }
    url = `${ADMIN_API}/users/create`;
    method = 'POST';
  }

  const res = await fetch(url, {
    method,
    headers: getAdminHeaders(),
    body: JSON.stringify(body)
  });
  const data = await res.json();

  if (data.success) {
    closeUserModal();
    loadUsers();
    showSuccessNotification(
      isEdit ? 'Cập nhật thành công!' : 'Thêm thành công!',
      `Người dùng "<strong>${body.name}</strong>" đã được ${isEdit ? 'cập nhật' : 'thêm vào'}`,
      body.name
    );
  } else {
    showToast('❌ ' + data.message);
  }
}

async function editUser(id) {
  const result = await fetchAdmin('/users');
  if (!result.success || !result.data) return;
  const user = result.data.find(u => u.id == id);
  if (user) openUserModal(user);
}

// ===== MESSAGES =====
let currentMessageId = null;

async function loadMessages() {
  const result = await fetchAdmin('/messages');
  const inbox = document.getElementById('msgInbox');
  const data = result.data || [];

  // Stats
  document.getElementById('msgStatTotal').textContent = data.length;
  document.getElementById('msgStatPending').textContent = data.filter(m => !m.reply).length;
  document.getElementById('msgStatReplied').textContent = data.filter(m => m.reply).length;

  if (!result.success || data.length === 0) {
    inbox.innerHTML = `
      <div class="msg-empty">
        <span class="msg-empty-icon">💬</span>
        <h3>Chưa có tin nhắn nào</h3>
        <p>Tin nhắn từ khách hàng sẽ hiển thị ở đây</p>
      </div>`;
    return;
  }

  inbox.innerHTML = data.map(m => {
    const hasReply = !!m.reply;
    const initial = (m.name || '?')[0].toUpperCase();
    return `
      <div class="msg-card ${hasReply ? '' : 'unread'}" onclick="openMessageModal(${m.id})">
        <div class="msg-card-avatar">${initial}</div>
        <div class="msg-card-body">
          <div class="msg-card-top">
            <span class="msg-card-name">${m.name}</span>
            <span class="msg-card-email">${m.email}</span>
            <span class="msg-card-date">${formatDate(m.created_at)}</span>
          </div>
          <div class="msg-card-text">${m.message}</div>
          <div class="msg-card-footer">
            <span class="msg-card-status ${hasReply ? 'replied' : 'pending'}">
              ${hasReply ? '✅ Đã phản hồi' : '🕐 Chờ phản hồi'}
            </span>
            <div class="msg-card-actions">
              <button class="msg-card-action delete" onclick="event.stopPropagation(); openDeleteModal('message',${m.id},'${m.name.replace(/'/g, "\\'")}')" title="Xóa">🗑️</button>
            </div>
          </div>
        </div>
      </div>`;
  }).join('');
}

async function openMessageModal(id) {
  currentMessageId = id;
  const result = await fetchAdmin('/messages');
  if (!result.success || !result.data) return;
  const msg = result.data.find(m => m.id == id);
  if (!msg) return;

  const initial = (msg.name || '?')[0].toUpperCase();
  document.getElementById('msgSenderAvatar').textContent = initial;
  document.getElementById('msgSenderName').textContent = msg.name;
  document.getElementById('msgSenderEmail').textContent = msg.email;
  document.getElementById('msgSenderDate').textContent = formatDate(msg.created_at);
  document.getElementById('msgContentText').textContent = msg.message;

  const hasReply = !!msg.reply;
  const statusEl = document.getElementById('msgReplyStatus');
  statusEl.textContent = hasReply ? '✅ Đã phản hồi' : '🕐 Chưa phản hồi';
  statusEl.className = 'msg-reply-status ' + (hasReply ? 'replied' : 'pending');

  document.getElementById('msgReplyInput').value = '';
  document.getElementById('msgReplyInput').style.display = 'block';

  const prevEl = document.getElementById('msgReplyPrev');
  if (hasReply) {
    document.getElementById('msgReplyPrevText').textContent = msg.reply;
    prevEl.style.display = 'block';
  } else {
    prevEl.style.display = 'none';
  }

  document.getElementById('messageModal').classList.add('active');
}

function closeMessageModal() {
  document.getElementById('messageModal').classList.remove('active');
  currentMessageId = null;
}

async function replyMessage() {
  const reply = document.getElementById('msgReplyInput').value.trim();
  if (!reply) {
    showToast('⚠️ Vui lòng nhập nội dung phản hồi');
    return;
  }
  if (!currentMessageId) return;

  const res = await fetch(`${ADMIN_API}/messages/reply`, {
    method: 'POST',
    headers: getAdminHeaders(),
    body: JSON.stringify({ id: currentMessageId, reply })
  });
  const data = await res.json();

  if (data.success) {
    showToast('✅ Đã gửi phản hồi thành công');
    loadMessages();
    closeMessageModal();
    showSuccessNotification('Phản hồi thành công!', 'Tin nhắn đã được phản hồi', '💬');
  } else {
    showToast('❌ ' + data.message);
  }
}

async function deleteMessage() {
  if (!currentMessageId) return;
  const res = await fetch(`${ADMIN_API}/messages/delete`, {
    method: 'DELETE',
    headers: getAdminHeaders(),
    body: JSON.stringify({ id: currentMessageId })
  });
  const data = await res.json();
  if (data.success) {
    showToast('✅ Đã xóa tin nhắn');
    loadMessages();
    closeMessageModal();
  } else {
    showToast('❌ ' + data.message);
  }
}

// ===== SHIPPING RATES CRUD =====
let shippingRatesCache = [];

async function loadShippingRates() {
  const tbody = document.getElementById('shippingRatesBody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="6" class="table-empty" style="padding:24px;text-align:center;color:var(--muted);">Đang tải...</td></tr>';

  try {
    const result = await fetchAdmin('/shipping-rates', { timeoutMs: 10000 });
    if (!result || !result.success) {
      const msg = (result && result.message) || 'Không tải được dữ liệu';
      tbody.innerHTML = `<tr><td colspan="6" class="table-empty" style="padding:24px;text-align:center;color:var(--signal);">
        ${msg}<br>
        <button class="btn btn-secondary" style="margin-top:12px;" onclick="loadShippingRates()">Thử lại</button>
      </td></tr>`;
      showToast('❌ ' + msg);
      return;
    }

    const data = Array.isArray(result.data) ? result.data : [];
    shippingRatesCache = data;

    if (data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="table-empty" style="padding:24px;text-align:center;color:var(--muted);">Chưa có gói phí vận chuyển. Bấm \"+ Thêm phí vận chuyển\".</td></tr>';
      return;
    }

    const fmt = (n) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(n) || 0);
    const esc = (s) => String(s ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');

    tbody.innerHTML = data.map(r => `
      <tr>
        <td style="padding:14px 16px;"><strong>${esc(r.name)}</strong></td>
        <td style="padding:14px 16px;text-align:right;font-weight:700;">${fmt(r.fee)}</td>
        <td style="padding:14px 16px;text-align:right;">${Number(r.free_shipping_threshold) > 0 ? fmt(r.free_shipping_threshold) : '—'}</td>
        <td style="padding:14px 16px;text-align:center;">
          <span style="font-size:12px;font-weight:700;padding:4px 10px;border-radius:999px;background:${Number(r.is_active) ? '#d1fae5' : '#fee2e2'};color:${Number(r.is_active) ? '#065f46' : '#991b1b'};">
            ${Number(r.is_active) ? 'Hoạt động' : 'Tắt'}
          </span>
        </td>
        <td style="padding:14px 16px;text-align:center;">${Number(r.is_default) ? '⭐' : '—'}</td>
        <td style="padding:14px 16px;text-align:right;">
          <button class="btn btn-secondary" style="padding:6px 10px;margin-right:6px;" onclick="editShippingRate(${Number(r.id)})">Sửa</button>
          <button class="btn" style="padding:6px 10px;color:var(--signal);background:transparent;" onclick="openDeleteModal('shipping', ${Number(r.id)}, '${esc(r.name)}')">Xóa</button>
        </td>
      </tr>
    `).join('');
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="6" class="table-empty" style="padding:24px;text-align:center;color:var(--signal);">${e.message || 'Lỗi không xác định'}</td></tr>`;
  }
}

function openShippingModal(rate = null) {
  const modal = document.getElementById('shippingModal');
  if (!modal) {
    showToast('❌ Không tìm thấy form phí vận chuyển');
    return;
  }
  document.getElementById('shipRateId').value = rate?.id || '';
  document.getElementById('shipRateName').value = rate?.name || '';
  document.getElementById('shipRateFee').value = rate?.fee ?? 0;
  document.getElementById('shipRateThreshold').value = rate?.free_shipping_threshold ?? 0;
  document.getElementById('shipRateActive').checked = rate ? Number(rate.is_active) === 1 : true;
  document.getElementById('shipRateDefault').checked = rate ? Number(rate.is_default) === 1 : (shippingRatesCache.length === 0);
  document.getElementById('shippingModalTitle').textContent = rate ? 'Sửa phí vận chuyển' : 'Thêm phí vận chuyển';
  document.getElementById('shippingModalSub').textContent = rate ? 'Cập nhật thông tin gói phí ship' : 'Tạo gói phí ship mới';
  modal.classList.add('active');
}

function closeShippingModal() {
  const modal = document.getElementById('shippingModal');
  if (modal) modal.classList.remove('active');
}

function editShippingRate(id) {
  const rate = shippingRatesCache.find(r => Number(r.id) === Number(id));
  if (rate) openShippingModal(rate);
  else showToast('❌ Không tìm thấy gói phí');
}

async function saveShippingRate(e) {
  if (e && e.preventDefault) e.preventDefault();
  const id = document.getElementById('shipRateId').value;
  const body = {
    name: document.getElementById('shipRateName').value.trim(),
    fee: Number(document.getElementById('shipRateFee').value),
    free_shipping_threshold: Number(document.getElementById('shipRateThreshold').value),
    is_active: document.getElementById('shipRateActive').checked ? 1 : 0,
    is_default: document.getElementById('shipRateDefault').checked ? 1 : 0
  };

  if (!body.name) {
    showToast('❌ Vui lòng nhập tên gói');
    return false;
  }
  if (!Number.isFinite(body.fee) || body.fee < 0) {
    showToast('❌ Phí vận chuyển không hợp lệ');
    return false;
  }

  const btn = document.getElementById('btnSaveShipRate');
  if (btn) {
    btn.disabled = true;
    btn.textContent = '⏳ Đang lưu...';
  }

  try {
    const data = await fetchAdmin('/shipping-rates', {
      method: id ? 'PUT' : 'POST',
      body: JSON.stringify(id ? { ...body, id: Number(id) } : body),
      timeoutMs: 12000
    });
    if (data.success) {
      showToast(id ? '✅ Đã cập nhật phí vận chuyển' : '✅ Đã thêm phí vận chuyển');
      closeShippingModal();
      loadShippingRates();
    } else {
      showToast('❌ ' + (data.message || 'Lưu thất bại'));
    }
  } catch (err) {
    showToast('❌ Không thể kết nối máy chủ');
  }

  if (btn) {
    btn.disabled = false;
    btn.textContent = '💾 Lưu';
  }
  return false;
}

window.openShippingModal = openShippingModal;
window.closeShippingModal = closeShippingModal;
window.loadShippingRates = loadShippingRates;
window.saveShippingRate = saveShippingRate;
window.editShippingRate = editShippingRate;

// ===== INIT =====
document.addEventListener('DOMContentLoaded', async () => {
  const user = await checkAdminAuth();
  if (!user) return;

  // Update user info
  document.getElementById('adminName').textContent = user.name;
  document.getElementById('adminEmail').textContent = user.email;
  document.getElementById('adminAvatar').textContent = user.name[0].toUpperCase();

  initSidebar();

  // Load all data
  loadStats();
  loadOrders();
  loadSliders();
  loadProjects();
  loadProducts();
  loadUsers();
  loadMessages();
  loadShippingRates();
});
