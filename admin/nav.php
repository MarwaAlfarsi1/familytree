<!-- admin/nav.php -->
<nav class="admin-nav">
    <div class="nav-container">
        <a href="dashboard_new.php" class="logo">
            <i class="fas fa-tree"></i>
            <span>لوحة التحكم</span>
        </a>
        
        <ul class="nav-links">
            <li><a href="dashboard_new.php"><i class="fas fa-th-large"></i> الرئيسية</a></li>
            <li><a href="manage_people_new.php"><i class="fas fa-users"></i> الأفراد</a></li>
            <li><a href="view_tree_classic.php"><i class="fas fa-network-wired"></i> الشجرة</a></li>
            <li><a href="member_profiles_list.php"><i class="fas fa-id-card"></i> الملفات</a></li>
            <li><a href="search.php"><i class="fas fa-search"></i> البحث</a></li>
            <li><a href="manage_requests.php"><i class="fas fa-user-plus"></i> الطلبات</a></li>
            <li><a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> خروج</a></li>
        </ul>

        <div class="nav-toggle">
            <i class="fas fa-bars"></i>
        </div>
    </div>
</nav>

<style>
.admin-nav {
    background: #3c2f2f;
    padding: 15px 0;
    font-family: 'Cairo', sans-serif;
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    border-bottom: 3px solid #f2c200;
}

.nav-container {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 20px;
}

.logo {
    color: #f2c200;
    text-decoration: none;
    font-size: 22px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
}

.nav-links {
    display: flex;
    list-style: none;
    gap: 15px;
    align-items: center;
}

.nav-links li a {
    color: #fff;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    padding: 8px 15px;
    border-radius: 20px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.nav-links li a:hover {
    background: rgba(242, 194, 0, 0.15);
    color: #f2c200;
}

.logout-btn {
    background: rgba(255, 255, 255, 0.1);
}

.logout-btn:hover {
    background: #e74c3c !important;
    color: #fff !important;
}

.nav-toggle {
    display: none;
    color: #f2c200;
    font-size: 24px;
    cursor: pointer;
}

@media (max-width: 992px) {
    .nav-links {
        display: none; /* يمكن إضافة JS للتبديل */
    }
    .nav-toggle {
        display: block;
    }
}
</style>

<script>
document.querySelector('.nav-toggle')?.addEventListener('click', function() {
    const links = document.querySelector('.nav-links');
    if (links.style.display === 'flex') {
        links.style.display = 'none';
    } else {
        links.style.display = 'flex';
        links.style.flexDirection = 'column';
        links.style.position = 'absolute';
        links.style.top = '100%';
        links.style.left = '0';
        links.style.width = '100%';
        links.style.background = '#3c2f2f';
        links.style.padding = '20px';
    }
});
</script>