terraform {
  backend "s3" {
    bucket  = "gci-tfstate-andrefillype-2026"
    key     = "terraform.tfstate"
    region  = "us-east-2"
    encrypt = true
  }
}