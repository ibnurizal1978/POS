// Animasi sederhana saat submit form
document.querySelector('form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const button = this.querySelector('button');
    button.classList.add('opacity-50');
    
    // Simulasi loading
    setTimeout(() => {
        button.classList.remove('opacity-50');
        // Di sini Anda bisa menambahkan logika login yang sebenarnya
    }, 1000);
});