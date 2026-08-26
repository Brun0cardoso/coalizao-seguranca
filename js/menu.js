const menuToggle = document.getElementById("menu-toggle");

const menu = document.getElementById("menu");


if (menuToggle && menu) {

    const fecharMenu = function () {
        menu.classList.remove("active");
        menuToggle.setAttribute("aria-expanded", "false");
    };

    menuToggle.addEventListener("click", function () {

        menu.classList.toggle("active");

        const aberto = menu.classList.contains("active");

        menuToggle.setAttribute(
            "aria-expanded",
            aberto
        );

    });

    menu.querySelectorAll("a").forEach(function (link) {
        link.addEventListener("click", fecharMenu);
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            fecharMenu();
        }
    });

}
