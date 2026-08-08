const menuToggle = document.getElementById("menu-toggle");

const menu = document.getElementById("menu");


if (menuToggle && menu) {

    menuToggle.addEventListener("click", function () {

        menu.classList.toggle("active");

        const aberto = menu.classList.contains("active");

        menuToggle.setAttribute(
            "aria-expanded",
            aberto
        );

    });

}