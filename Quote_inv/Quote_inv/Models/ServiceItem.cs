using System.ComponentModel.DataAnnotations;

namespace Quote_inv.Models
{
    public class ServiceItem
    {
        public int Id { get; set; }

        [Required]
        public int QuotationId { get; set; }
        public Quotation Quotation { get; set; }

        [Required]
        public string ServiceName { get; set; }

        [Required]
        public string Description { get; set; }

        [Required]
        [Range(0.01, double.MaxValue)]
        public decimal Price { get; set; }
    }
}
