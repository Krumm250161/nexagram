// Apply saved theme immediately on load to prevent flickering
(function() {
  const savedTheme = localStorage.getItem('theme');
  if (savedTheme === 'dark') {
    document.body.classList.add('dark-mode');
  }
})();

document.addEventListener('DOMContentLoaded', () => {
  const themeToggleBtns = document.querySelectorAll('.theme-toggle-btn');

  // Update button icon state based on current class
  function updateIcons() {
    const isDark = document.body.classList.contains('dark-mode');
    themeToggleBtns.forEach(btn => {
      btn.innerHTML = isDark ? '☀️' : '🌙';
    });
  }

  updateIcons();

  // Handle click event for toggle button
  themeToggleBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      document.body.classList.toggle('dark-mode');
      const isDark = document.body.classList.contains('dark-mode');
      localStorage.setItem('theme', isDark ? 'dark' : 'light');
      updateIcons();
    });
  });
});