using Microsoft.AspNetCore.Identity.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore;
using Quote_inv.Models;  // Make sure this using exists

namespace Quote_inv.Areas.Identity.Data
{
    public class qandiContext : IdentityDbContext
    {
        public qandiContext(DbContextOptions<qandiContext> options)
            : base(options)
        {
        }

        // Add these DbSets
        public DbSet<Client> Clients { get; set; }
        public DbSet<Quotation> Quotations { get; set; }
        public DbSet<ServiceItem> ServiceItems { get; set; }
        public DbSet<ProductItem> ProductItems { get; set; }

        protected override void OnModelCreating(ModelBuilder builder)
        {
            base.OnModelCreating(builder);

            builder.Entity<Quotation>()
                .HasOne(q => q.Client)
                .WithMany()
                .HasForeignKey(q => q.ClientId);

            builder.Entity<ServiceItem>()
                .HasOne(s => s.Quotation)
                .WithMany(q => q.ServiceItems)
                .HasForeignKey(s => s.QuotationId)
                .OnDelete(DeleteBehavior.Cascade);

            builder.Entity<ProductItem>()
                .HasOne(p => p.Quotation)
                .WithMany(q => q.ProductItems)
                .HasForeignKey(p => p.QuotationId)
                .OnDelete(DeleteBehavior.Cascade);
        }
    }
}