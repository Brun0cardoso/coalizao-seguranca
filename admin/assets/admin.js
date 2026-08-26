const menuButton = document.querySelector('.menu-button');
const sidebar = document.querySelector('.sidebar');

if (menuButton && sidebar) {
    menuButton.addEventListener('click', () => {
        const isOpen = sidebar.classList.toggle('is-open');
        menuButton.setAttribute('aria-expanded', String(isOpen));
    });
}
