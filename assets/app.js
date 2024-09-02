import './styles/app.css';

document.addEventListener('DOMContentLoaded', function() {
    const menuButton = document.querySelector('#burger-menu-button');
    const closeButton = document.querySelector('#close-menu-button');
    const offCanvasMenu = document.querySelector('#off-canvas-menu');
    const offCanvasMenuMobile = document.querySelector('#off-canvas-menu-mobile');
    const backdrop = document.querySelector('#menu-backdrop');

    function openMenu() {
        offCanvasMenuMobile.classList.remove("hidden");
        offCanvasMenu.classList.remove('-translate-x-full', 'opacity-0');
        offCanvasMenu.classList.add('translate-x-0', 'opacity-100');
        backdrop.classList.remove('opacity-0');
        backdrop.classList.add('opacity-100');
    }

    function closeMenu() {
        offCanvasMenuMobile.classList.add("hidden");

        offCanvasMenu.classList.remove('translate-x-0', 'opacity-100');
        offCanvasMenu.classList.add('-translate-x-full', 'opacity-0');
        backdrop.classList.remove('opacity-100');
        backdrop.classList.add('opacity-0');
    }

    menuButton.addEventListener('click', openMenu);
    closeButton.addEventListener('click', closeMenu);

    backdrop.addEventListener('click', closeMenu);
});