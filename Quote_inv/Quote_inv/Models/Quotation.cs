using System;
using System.Collections.Generic;
using System.ComponentModel.DataAnnotations;
using Microsoft.Build.Evaluation;

namespace Quote_inv.Models
{
    public class Quotation
    {
        public int Id { get; set; }

        [Required]
        [Display(Name = "Quotation ID")]
        public string QuotationId { get; set; } = "QT-" + DateTime.Now.ToString("yyyyMMddHHmmss");

        [Required]
        public int ClientId { get; set; }
        public Client Client { get; set; }

        [Required]
        [Display(Name = "Date Created")]
        public DateTime DateCreated { get; set; } = DateTime.Now;

        [Required]
        public bool IsService { get; set; } = true;

        public decimal TotalAmount { get; set; }

        // Navigation properties
        public List<ServiceItem> ServiceItems { get; set; } = new List<ServiceItem>();
        public List<ProductItem> ProductItems { get; set; } = new List<ProductItem>();
    }
}
